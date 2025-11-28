<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class CogUploadController extends Controller
{
  /**
   * Upload COG (PDF or Image), render preview PNG, and (for PDF) extract real text
   * into storage/app/cog/cog_ocr_output.txt using pdftotext (Poppler/XPDF) or Smalot\PdfParser.
   */
  public function upload(Request $request)
  {
    @set_time_limit(300);

    $hasPdf   = $request->hasFile('pdf');
    $hasImage = $request->hasFile('image');

    if (!$hasPdf && !$hasImage) {
      return response()->json(['ok'=>false,'error'=>'No file uploaded. Use field "pdf" or "image".'], 422);
    }

    if ($hasPdf) {
      $request->validate(['pdf' => 'required|file|mimes:pdf|max:25600']);
    } else {
      $request->validate(['image' => 'required|file|mimes:png,jpg,jpeg|max:25600']);
    }

    $dir = storage_path('app/cog');
    File::ensureDirectoryExists($dir);

    $finalPng = $dir.DIRECTORY_SEPARATOR.'cog_upload.png';
    $finalPdf = $dir.DIRECTORY_SEPARATOR.'cog_upload.pdf';
    $ocrTxt   = $dir.DIRECTORY_SEPARATOR.'cog_ocr_output.txt';

    // clean current preview assets only
    @unlink($finalPng);
    if ($hasPdf) @unlink($finalPdf);

    try {
      // 1) Save file
      if ($hasPdf) {
        $request->file('pdf')->move($dir, 'cog_upload.pdf');

        // 2) Render page 1 to PNG
        $rendered = $this->renderPdfPage1ToPng($finalPdf, $finalPng);
        if (!$rendered) {
          return response()->json(['ok'=>false,'error'=>'Unable to render PDF preview.'], 500);
        }

        // 3) Extract REAL TEXT from PDF and write to cog_ocr_output.txt
        $text = $this->extractTextFromPdfSmart($finalPdf);
        File::put($ocrTxt, $text);

      } else {
        // IMAGE path (no text extraction here)
        $tmp = $request->file('image')->move($dir, 'cog_upload.tmp');
        try {
          $this->forceToPng((string)$tmp, $finalPng);
        } finally {
          @unlink((string)$tmp);
        }
      }

      // 4) Crop top content area for nicer preview (no parsing here)
      $this->cogTopContentCrop($finalPng, $finalPng);

      // 5) Publish preview to /storage/uploads/cog/
      $pubRel = 'uploads/cog/cog_upload.png';
      $pubAbs = storage_path('app/public/'.$pubRel);
      File::ensureDirectoryExists(dirname($pubAbs));
      @copy($finalPng, $pubAbs);

      return response()->json([
        'ok'            => true,
        'message'       => 'COG uploaded (preview ready)'.($hasPdf ? ' and text extracted.' : '.'),
        'cog_image_url' => asset('storage/'.$pubRel),
        'cog_image_path'=> $finalPng,
        'pdf_path'      => $hasPdf ? $finalPdf : null,
        'ocr_text_path' => $hasPdf ? $ocrTxt : null,
        'text_bytes'    => ($hasPdf && is_file($ocrTxt)) ? filesize($ocrTxt) : 0,
      ]);
    } catch (\Throwable $e) {
      Log::error('COG upload error', ['err'=>$e->getMessage()]);
      return response()->json(['ok'=>false,'error'=>'Upload failed.'], 500);
    }
  }

  /* ========================= Text Extraction ========================= */

  /** Try Poppler/XPDF pdftotext, then Smalot\PdfParser as fallback. Returns text or ''. */
  private function extractTextFromPdfSmart(string $pdfAbs): string
  {
    if (!is_file($pdfAbs)) return '';

    // Poppler pdftotext (POPPLER_BIN=/usr/bin etc.)
    $poppler = trim((string) env('POPPLER_BIN', ''));
    $pdftotext = $poppler ? rtrim($poppler, '/').'/pdftotext' : 'pdftotext';
    $txt = $this->runPdftotext($pdftotext, $pdfAbs);
    if ($txt !== '') return $txt;

    // XPDF pdftotext (XPDF_BIN_DIR=/home/user/opt/xpdf-tools-linux-4.xx/bin64)
    $xpdf = trim((string) env('XPDF_BIN_DIR', ''));
    if ($xpdf !== '') {
      $pdftotextX = rtrim($xpdf, '/').'/pdftotext';
      $txt = $this->runPdftotext($pdftotextX, $pdfAbs);
      if ($txt !== '') return $txt;
    }

    // PHP fallback: Smalot\PdfParser
    try {
      if (class_exists(\Smalot\PdfParser\Parser::class)) {
        $parser = new \Smalot\PdfParser\Parser();
        $doc    = $parser->parseFile($pdfAbs);
        $t      = $doc->getText();
        return is_string($t) ? trim($t) : '';
      }
      Log::warning('PDF text fallback unavailable: Smalot\\PdfParser not installed');
    } catch (\Throwable $e) {
      Log::warning('Smalot PdfParser failed', ['err' => $e->getMessage()]);
    }

    return '';
  }

  /** Run a pdftotext binary; return the text ('' on failure). */
  private function runPdftotext(string $bin, string $pdfAbs): string
  {
    try {
      // Check binary presence
      $ver = @shell_exec(escapeshellcmd($bin).' -v 2>&1');
      if ($ver === null) return '';

      // Prefer stdout
      $cmd = escapeshellcmd($bin).' -layout '.escapeshellarg($pdfAbs).' -';
      $out = @shell_exec($cmd.' 2>/dev/null');
      if (is_string($out) && trim($out) !== '') return trim($out);

      // Fallback to temp file
      $tmp = storage_path('app/tmp/'.\Illuminate\Support\Str::random(10).'.txt');
      File::ensureDirectoryExists(dirname($tmp));
      $cmd2 = escapeshellcmd($bin).' -layout '.escapeshellarg($pdfAbs).' '.escapeshellarg($tmp);
      @shell_exec($cmd2.' 2>/dev/null');
      if (is_file($tmp)) {
        $txt = (string) File::get($tmp);
        @unlink($tmp);
        return trim($txt);
      }
    } catch (\Throwable $e) {
      Log::warning('pdftotext failed', ['bin'=>$bin, 'err'=>$e->getMessage()]);
    }
    return '';
  }

  /* ========================= Preview Helpers (unchanged) ========================= */

  private function renderPdfPage1ToPng(string $pdfAbs, string $outPngAbs): bool
  {
    $ok = false;

    // Prefer poppler pdftoppm
    $bin = $this->findBinary('pdftoppm');
    if ($bin) {
      try {
        $noExt = $outPngAbs.'.page1';
        $this->run([$bin, '-png', '-singlefile', '-r', '200', $pdfAbs, $noExt], 90);
        $cand = $noExt.'.png';
        if (is_file($cand)) { @rename($cand, $outPngAbs); $ok = true; }
      } catch (\Throwable $e) {/* continue */}
    }

    // Xpdf pdftopng
    if (!$ok && ($bin = $this->findBinary('pdftopng'))) {
      try {
        $prefix = $outPngAbs.'.page';
        $this->run([$bin, '-r', '200', $pdfAbs, $prefix], 90);
        $cand = $prefix.'-000001.png';
        if (!is_file($cand)) {
          $matches = glob($prefix.'*.png') ?: [];
          $cand = $matches ? $matches[0] : null;
        }
        if ($cand && is_file($cand)) { @rename($cand, $outPngAbs); $ok = true; }
        foreach (glob($prefix.'*.png') ?: [] as $g) { @unlink($g); }
      } catch (\Throwable $e) {/* continue */}
    }

    // Imagick fallback
    if (!$ok && extension_loaded('imagick')) {
      try {
        $im = new \Imagick();
        $im->setResolution(200,200);
        $im->readImage($pdfAbs.'[0]');
        $im->setImageBackgroundColor('white');
        $im->setImageAlphaChannel(\Imagick::ALPHACHANNEL_REMOVE);
        $im = $im->mergeImageLayers(\Imagick::LAYERMETHOD_FLATTEN);
        $im->setImageFormat('png');
        $im->writeImage($outPngAbs);
        $im->clear(); $im->destroy();
        $ok = is_file($outPngAbs);
      } catch (\Throwable $e) {}
    }

    return $ok;
  }

  private function cogTopContentCrop(string $srcPng, string $dstPng): int
  {
    $left   = (float) env('COG_CROP_LEFT',   0.05);
    $right  = (float) env('COG_CROP_RIGHT',  0.96);
    $top    = (float) env('COG_CROP_TOP',    0.04);
    $bottom = (float) env('COG_CROP_BOTTOM', 0.495);

    $left   = max(0.0,  min($left,  0.49));
    $right  = max(0.51, min($right, 1.0));
    $top    = max(0.0,  min($top,   0.49));
    $bottom = max(0.51, min($bottom,1.0));

    try { [$w, $h] = getimagesize($srcPng); }
    catch (\Throwable $e) {
      Log::warning('getimagesize failed', ['err' => $e->getMessage()]);
      return -1;
    }

    $x  = (int) floor($left   * $w);
    $y  = (int) floor($top    * $h);
    $cw = (int) ceil(($right  - $left)  * $w);
    $ch = (int) ceil(($bottom - $top)   * $h);

    // Imagick
    if (extension_loaded('imagick')) {
      try {
        $im = new \Imagick($srcPng);
        $im->cropImage($cw, $ch, $x, $y);
        $im->setImagePage(0,0,0,0);
        $im->setImageFormat('png');
        $im->borderImage('white', 12, 12);
        $im->writeImage($dstPng);
        $im->clear(); $im->destroy();
        return is_file($dstPng) ? 1 : -1;
      } catch (\Throwable $e) {
        Log::warning('Imagick crop failed', ['err' => $e->getMessage()]);
      }
    }

    // GD fallback
    if (function_exists('imagecreatefrompng')) {
      try {
        $src = imagecreatefrompng($srcPng);
        if (!$src) return -1;
        $dst = imagecreatetruecolor($cw, $ch);
        $white = imagecolorallocate($dst, 255,255,255);
        imagefill($dst, 0, 0, $white);
        imagecopy($dst, $src, 0, 0, $x, $y, $cw, $ch);

        $border = 12;
        $withB  = imagecreatetruecolor($cw + 2*$border, $ch + 2*$border);
        $white2 = imagecolorallocate($withB, 255,255,255);
        imagefill($withB, 0, 0, $white2);
        imagecopy($withB, $dst, $border, $border, 0, 0, $cw, $ch);

        imagepng($withB, $dstPng, 6);
        imagedestroy($withB);
        imagedestroy($dst);
        imagedestroy($src);

        return is_file($dstPng) ? 1 : -1;
      } catch (\Throwable $e) {
        Log::warning('GD crop failed', ['err' => $e->getMessage()]);
      }
    }

    // CLI fallback (ImageMagick convert)
    if ($this->tryCliCropWithImageMagick($srcPng, $dstPng, $x, $y, $cw, $ch)) {
      return 1;
    }

    @copy($srcPng, $dstPng);
    Log::warning('COG cropping unavailable; copied full page as preview.');
    return is_file($dstPng) ? 0 : -1;
  }

  private function forceToPng(string $srcAbs, string $dstAbs): void
  {
    $ext = strtolower(pathinfo($srcAbs, PATHINFO_EXTENSION));
    if ($ext === 'png') { @copy($srcAbs, $dstAbs); return; }
    if (!extension_loaded('imagick')) { @copy($srcAbs, $dstAbs); return; }
    try {
      $im = new \Imagick($srcAbs);
      $im->setImageColorspace(\Imagick::COLORSPACE_RGB);
      $im->setImageAlphaChannel(\Imagick::ALPHACHANNEL_REMOVE);
      $im->mergeImageLayers(\Imagick::LAYERMETHOD_FLATTEN);
      $im->setImageFormat('png');
      $im->writeImage($dstAbs);
      $im->clear(); $im->destroy();
    } catch (\Throwable $e) {
      @copy($srcAbs, $dstAbs);
    }
  }

  private function findBinary(string $name): ?string
  {
    $isWin = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
    try {
      $cmd = $isWin ? ['where', $name] : ['which', $name];
      $out = $this->run($cmd, 4);
      $first = trim(preg_split('/\R/', $out)[0] ?? '');
      if ($first && is_file($first)) return $first;
    } catch (\Throwable $e) {}
    return null;
  }

  private function run(array $cmd, int $timeout = 30): string
  {
    $p = proc_open($cmd, [['pipe','r'],['pipe','w'],['pipe','w']], $pipes);
    if (!is_resource($p)) throw new \RuntimeException('proc_open failed');
    foreach ($pipes as $h) stream_set_blocking($h, true);
    $start = microtime(true); $out = ''; $err = '';
    while (true) {
      $out .= stream_get_contents($pipes[1]);
      $err .= stream_get_contents($pipes[2]);
      $st = proc_get_status($p);
      if (!$st['running']) break;
      if ((microtime(true) - $start) > $timeout) { proc_terminate($p); break; }
      usleep(30000);
    }
    foreach ($pipes as $h) @fclose($h);
    $code = proc_close($p);
    if ($code !== 0 && $err) throw new \RuntimeException($err);
    return $out.$err;
  }

  /* optional CLI crop using ImageMagick's convert (used only if Imagick/GD fail) */
  private function tryCliCropWithImageMagick(string $src, string $dst, int $x, int $y, int $w, int $h): bool
  {
    try {
      $bin = $this->findBinary('convert');
      if (!$bin) return false;
      $cmd = [$bin, $src, '-crop', "{$w}x{$h}+{$x}+{$y}", '+repage', '-bordercolor', 'white', '-border', '12x12', $dst];
      $this->run($cmd, 30);
      return is_file($dst);
    } catch (\Throwable $e) { return false; }
  }
}
