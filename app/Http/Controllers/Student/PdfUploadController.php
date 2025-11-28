<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class PdfUploadController extends Controller
{
  public function upload(Request $request)
  {
    @set_time_limit(300);

    $request->validate([
      'pdf' => 'required|file|mimes:pdf|max:25600', // 25 MB
    ]);

    // Dirs
    $corDir = storage_path('app/cor');
    $cogDir = storage_path('app/cog'); // mirror target
    $pubDir = storage_path('app/public/uploads/cor');
    File::ensureDirectoryExists($corDir);
    File::ensureDirectoryExists($cogDir);
    File::ensureDirectoryExists($pubDir);

    // Save PDF (timestamp copy + canonical)
    $stamp    = now()->format('Ymd_His') . '_' . Str::random(5);
    $debugPdf = $corDir . DIRECTORY_SEPARATOR . "cor_{$stamp}.pdf";
    $fixedPdf = $corDir . DIRECTORY_SEPARATOR . 'cor_upload.pdf';
    $request->file('pdf')->move($corDir, basename($debugPdf));
    @copy($debugPdf, $fixedPdf);

    // Public preview
    $publicPdfAbs = $pubDir . DIRECTORY_SEPARATOR . 'cor_upload.pdf';
    @copy($fixedPdf, $publicPdfAbs);
    $publicPdfUrl = asset('storage/uploads/cor/cor_upload.pdf');

    // Render PNG preview
    $tmpPng   = $corDir . DIRECTORY_SEPARATOR . "cor_first_{$stamp}.png";
    $finalPng = $corDir . DIRECTORY_SEPARATOR . 'cor_upload.png';
    $rendered = $this->renderFirstPageToPng($fixedPdf, $tmpPng);

    $pngPublicUrl = null;
    $msg = 'Saved (PDF ready; PNG preview unavailable).';
    $publicPngAbs = null;

    if ($rendered) {
      $cropStatus = $this->cropCorRegion($tmpPng, $finalPng); // 1=cropped, 0=copied, -1=fail
      if ($cropStatus >= 0) {
        $publicPngAbs = $pubDir . DIRECTORY_SEPARATOR . 'cor_upload.png';
        @copy($finalPng, $publicPngAbs);
        $pngPublicUrl = asset('storage/uploads/cor/cor_upload.png');
        $msg = $cropStatus === 1
          ? 'Saved with cropped PNG preview.'
          : ($cropStatus === 0 ? 'Saved with full-page PNG preview (cropping unavailable).' : $msg);
      }
    }
    if (is_file($tmpPng)) @unlink($tmpPng);

    // Extract → parse → pretty ASCII (NO raw dump)
    $raw   = $this->extractTextToString($fixedPdf);
    $norm  = $this->normalize($raw);
    [$meta, $rows] = $this->parseCor($norm);            // meta + course rows
    $pretty = $this->buildAsciiLayout($meta, $rows);    // clean PDF-like text

    // Write to BOTH files (mirror)
    $corOutAbs = $corDir . DIRECTORY_SEPARATOR . 'cor_output.txt';
    $cogOutAbs = $cogDir . DIRECTORY_SEPARATOR . 'cog_output.txt';
    File::put($corOutAbs, $pretty);
    File::put($cogOutAbs, $pretty);

    Log::info('COR upload done (pretty only)', [
      'pdf_public_exists' => is_file($publicPdfAbs),
      'png_public_exists' => $publicPngAbs ? is_file($publicPngAbs) : false,
      'pdf_path'          => $fixedPdf,
      'text_path_cor'     => $corOutAbs,
      'text_path_cog'     => $cogOutAbs,
    ]);

    return response()->json([
      'ok'                   => true,
      'message'              => $msg,
      'cor_pdf_path'         => $fixedPdf,
      'cor_png_path'         => is_file($finalPng) ? $finalPng : null,
      'cor_text_path'        => $corOutAbs,
      'cog_text_path'        => $cogOutAbs,
      'cor_pdf_url'          => $publicPdfUrl,
      'cor_upload_png_url'   => $pngPublicUrl,
      'text_excerpt'         => mb_substr($pretty, 0, 200),
    ]);
  }

  /* ────────────────────── COR parsing ────────────────────── */

  /**
   * Parse important fields + the course table from normalized text.
   * Returns [meta, rows].
   */
  private function parseCor(string $t): array
  {
    $meta = [
      'fullname'      => '',
      'srcode'        => '',
      'sex'           => '',
      'program'       => '',
      'college'       => '',
      'semester'      => '',
      'academic_year' => '',
      'year_level'    => '', // not in COR; keep blank
    ];

    // College line (exact text line)
    if (preg_match('/^College of .*$/mi', $t, $m)) {
      $meta['college'] = trim($m[0]);
    }

    // Semester + AY e.g. "SECOND, 2023-2024"
    if (preg_match('/\b(FIRST|SECOND|SUMMER)\s*,\s*(\d{4}\s*[-–]\s*\d{4})/i', $t, $m)) {
      $meta['semester']      = strtoupper(trim($m[1]));
      $meta['academic_year'] = str_replace('–', '-', preg_replace('/\s+/', '', $m[2]));
    }

    // SR Code + Sex
    if (preg_match('/SR\s*Code\s*:\s*([0-9\-]+)\s+Sex\s*:\s*([A-Z]+)/i', $t, $m)) {
      $meta['srcode'] = preg_replace('/[^0-9]/', '', $m[1]);
      $meta['sex']    = strtoupper(trim($m[2]));
    }

    // Name + Program (usually on one line)
    if (preg_match('/Name\s*:\s*(.+?)\s+Program\s*:\s*(.+)$/im', $t, $m)) {
      $meta['fullname'] = $this->cleanInline($m[1]);
      $meta['program']  = $this->cleanInline($m[2]);
    } else {
      if (preg_match('/Name\s*:\s*(.+)$/im', $t, $mN)) $meta['fullname'] = $this->cleanInline($mN[1]);
      if (preg_match('/Program\s*:\s*(.+)$/im', $t, $mP)) $meta['program'] = $this->cleanInline($mP[1]);
    }

    // Course table: start after "COURSE CODE" header, stop at "Scholarship" or fees
    $rows = [];
    $lines = preg_split('/\r?\n/', $t);
    $N = count($lines);
    $start = -1;
    for ($i=0; $i<$N; $i++) {
      if (stripos($lines[$i], 'COURSE CODE') !== false && stripos($lines[$i], 'COURSE TITLE') !== false) {
        $start = $i + 1; break;
      }
    }

    if ($start >= 0) {
      for ($i=$start; $i<$N; $i++) {
        $line = trim($lines[$i]);

        if ($line === '' || stripos($line, 'Scholarship') !== false || stripos($line, 'Tuition Fee') !== false) {
          break;
        }
        // Skip the sub-header "SECTION"
        if (preg_match('/^SECTION$/i', $line)) continue;

        // Examples:
        // "ES 101  Environmental Sciences 3 (IT-2203)"
        // "GEd 101  Understanding the Self 3 (IT-2203)"
        // "IT 223  Computer Networking 2 3 (IT-2203)"
        if (preg_match('/^([A-Z]{2,}\s?\d{2,4}[A-Z]?)\s+(.+?)\s+(\d+)\s+\(([A-Z0-9\-]+)\)/u', $line, $m)) {
          $rows[] = [
            'idx'        => count($rows) + 1,
            'code'       => trim($m[1]),
            'title'      => $this->cleanInline($m[2]),
            'units'      => (string) (int) $m[3],
            'grade'      => '', // COR has no grade per subject
            'section'    => trim($m[4]),
            'instructor' => '',
          ];
          continue;
        }

        // Looser fallback: code + title + units (no section)
        if (preg_match('/^([A-Z]{2,}\s?\d{2,4}[A-Z]?)\s+(.+?)\s+(\d+)\s*$/u', $line, $m2)) {
          $rows[] = [
            'idx'        => count($rows) + 1,
            'code'       => trim($m2[1]),
            'title'      => $this->cleanInline($m2[2]),
            'units'      => (string) (int) $m2[3],
            'grade'      => '',
            'section'    => '',
            'instructor' => '',
          ];
        }
      }
    }

    return [$meta, $rows];
  }

  /* ────────────────────── Pretty layout (no raw dump) ────────────────────── */

  private function buildAsciiLayout(array $meta, array $rows): string
  {
    // Totals
    $totalUnits = 0.0;
    foreach ($rows as $r) $totalUnits += (float)($r['units'] ?? 0);

    $pad  = fn($s,$w,$side='right') => ($side==='left' ? str_pad((string)$s,$w,' ',STR_PAD_LEFT) : str_pad((string)$s,$w));
    $center = function(string $s, int $W=99) {
      $s = trim($s); $len = strlen($s);
      if ($len >= $W) return $s;
      $lpad = intdiv($W - $len, 2);
      return str_repeat(' ', $lpad).$s;
    };

    $wIdx=2; $wCode=9; $wTitle=40; $wUnits=5; $wSect=10;

    $L = [];
    $L[] = $center('BATANGAS STATE UNIVERSITY');
    $L[] = $center('The National Engineering University');
    $L[] = $center('ARASOF-Nasugbu Campus');
    $L[] = $center($meta['college'] ?: ''); // exact line captured
    $L[] = '';
    $L[] = $center('REGISTRATION FORM');
    $L[] = $center(($meta['semester'] ? $meta['semester'].', ' : '') . ($meta['academic_year'] ?: ''));
    $L[] = '';

    // Two-column-ish meta (keep it compact, like your COG style)
    $rightCol = 70;
    $line1 = '    SR Code : '.($meta['srcode'] ?: '');
    $line1 = str_pad($line1, $rightCol) . 'Sex : '.($meta['sex'] ?: '');
    $L[] = $line1;

    $line2 = '     Name : '.($meta['fullname'] ?: '');
    $L[] = $line2;

    $line3 = '  Program : '.($meta['program'] ?: '');
    $L[] = $line3;
    $L[] = '';

    // Table header
    $L[] = '# '.$pad('Course Code', $wCode)
         . $pad('Course Title', $wTitle)
         . $pad('Units', $wUnits, 'left').'   '
         . $pad('Section', $wSect)
         . '   ';
    // Rows
    foreach (array_values($rows) as $i => $r) {
      $idx  = $pad($i+1, $wIdx, 'left').' ';
      $code = $pad($r['code'] ?? '', $wCode);
      $titl = $pad(substr($r['title'] ?? '', 0, $wTitle), $wTitle);
      $unit = $pad(is_numeric($r['units'] ?? null) ? (string)($r['units']) : '', $wUnits, 'left');
      $sect = $pad($r['section'] ?? '', $wSect);
      $L[]  = $idx.$code.$titl.'  '.$unit.'  '.$sect;
    }

    $L[] = '';
    $L[] = $center('** NOTHING FOLLOWS **');
    $L[] = str_pad(' ', 83).'Total no of Units '.$pad((int)$totalUnits, 20, 'left');
    $L[] = '';

    return implode("\n", $L)."\n";
  }

  /* ────────────────────── Text extract + normalize ────────────────────── */

  private function extractTextToString(string $pdfAbs): string
  {
    if ($bin = $this->findBinary('pdftotext')) {
      try {
        $tmp = storage_path('app/cor/_tmp_txt_'.Str::random(8).'.txt');
        File::ensureDirectoryExists(dirname($tmp));
        $this->runProcess([$bin, '-enc', 'UTF-8', '-layout', $pdfAbs, $tmp], 60);
        $txt = is_file($tmp) ? (string)file_get_contents($tmp) : '';
        @unlink($tmp);
        return $txt;
      } catch (\Throwable $e) {
        Log::warning('pdftotext failed', ['err' => $e->getMessage()]);
      }
    }
    return '';
  }

  private function normalize(string $s): string
  {
    $s = str_replace("\r\n", "\n", $s);
    $s = str_replace("\xC2\xA0", ' ', $s);           // nbsp
    $s = preg_replace('/[ \t]+/', ' ', $s);          // collapse spaces (keep newlines)
    $s = preg_replace("/\n{2,}/", "\n", $s);
    return trim($s);
  }

  private function cleanInline(string $s): string
  {
    $s = trim($s);
    $s = preg_replace('/\s{2,}/', ' ', $s);
    $s = preg_replace('/[^\P{C}\n]+/u', '', $s); // control chars
    return $s;
  }

  /* ────────────────────── PNG preview helpers ────────────────────── */

  private function renderFirstPageToPng(string $pdfAbs, string $outPngAbs): bool
  {
    // 1) Xpdf: pdftopng
    if ($bin = $this->findBinary('pdftopng')) {
      try {
        $root = $outPngAbs . '.xpdf';
        $this->runProcess([$bin, '-r', '300', '-f', '1', '-l', '1', $pdfAbs, $root], 120);
        $candidate = $root . '-000001.png';
        if (is_file($candidate)) { @rename($candidate, $outPngAbs); return true; }
      } catch (\Throwable $e) {
        \Log::warning('pdftopng (Xpdf) failed', ['err' => $e->getMessage()]);
      }
    }

    // 2) Poppler: pdftoppm -png -singlefile
    if ($bin = $this->findBinary('pdftoppm')) {
      try {
        $ver = '';
        try { $ver = trim($this->runProcess([$bin, '-v'], 4)); } catch (\Throwable $e) {}
        $isPoppler = stripos($ver, 'poppler') !== false;

        if ($isPoppler) {
          $noExt = $outPngAbs . '.poppler';
          $this->runProcess([$bin, '-png', '-singlefile', '-f', '1', '-l', '1', '-r', '300', $pdfAbs, $noExt], 120);
          $cand = $noExt . '.png';
          if (is_file($cand)) { @rename($cand, $outPngAbs); return true; }
        } else {
          \Log::warning('pdftoppm is Xpdf, skipping Poppler flags.');
        }
      } catch (\Throwable $e) {
        \Log::warning('pdftoppm failed', ['err' => $e->getMessage()]);
      }
    }

    // 3) Imagick fallback
    if (extension_loaded('imagick')) {
      try {
        $im = new \Imagick();
        $im->setResolution(300, 300);
        $im->readImage($pdfAbs . '[0]');
        $im->setImageBackgroundColor('white');
        $im->setImageAlphaChannel(\Imagick::ALPHACHANNEL_REMOVE);
        $im = $im->mergeImageLayers(\Imagick::LAYERMETHOD_FLATTEN);
        $im->setImageFormat('png');
        $im->writeImage($outPngAbs);
        $im->clear(); $im->destroy();
        return is_file($outPngAbs);
      } catch (\Throwable $e) {
        \Log::warning('Imagick render failed', ['err' => $e->getMessage()]);
      }
    }

    return false;
  }

  private function cropCorRegion(string $srcPng, string $dstPng): int
  {
    $left   = (float) env('COR_CROP_LEFT',   0.00);
    $right  = (float) env('COR_CROP_RIGHT',  0.970);
    $top    = (float) env('COR_CROP_TOP',    0.04);
    $bottom = (float) env('COR_CROP_BOTTOM', 0.52);

    $left   = max(0.0,  min($left,  0.49));
    $right  = max(0.51, min($right, 1.0));
    $top    = max(0.0,  min($top,   0.49));
    $bottom = max(0.51, min($bottom,1.0));

    try { [$w, $h] = getimagesize($srcPng); } catch (\Throwable $e) {
      Log::warning('getimagesize failed', ['err' => $e->getMessage()]);
      return -1;
    }

    $x  = (int) floor($left   * $w);
    $y  = (int) floor($top    * $h);
    $cw = (int) ceil(($right  - $left)  * $w);
    $ch = (int) ceil(($bottom - $top)   * $h);

    if (extension_loaded('imagick')) {
      try {
        $im = new \Imagick($srcPng);
        $im->cropImage($cw, $ch, $x, $y);
        $im->setImagePage(0, 0, 0, 0);
        $im->setImageFormat('png');
        $im->borderImage('white', 12, 12);
        $im->writeImage($dstPng);
        $im->clear(); $im->destroy();
        return is_file($dstPng) ? 1 : -1;
      } catch (\Throwable $e) {
        Log::warning('Imagick crop failed', ['err' => $e->getMessage()]);
      }
    }

    if (function_exists('imagecreatefrompng')) {
      try {
        $src = imagecreatefrompng($srcPng);
        if (!$src) return -1;

        $dst = imagecreatetruecolor($cw, $ch);
        $white = imagecolorallocate($dst, 255, 255, 255);
        imagefill($dst, 0, 0, $white);
        imagecopy($dst, $src, 0, 0, $x, $y, $cw, $ch);

        $border = 12;
        $withB  = imagecreatetruecolor($cw + 2 * $border, $ch + 2 * $border);
        $white2 = imagecolorallocate($withB, 255, 255, 255);
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

    if ($this->tryCliCropWithImageMagick($srcPng, $dstPng, $x, $y, $cw, $ch)) return 1;

    @copy($srcPng, $dstPng);
    Log::warning('Cropping unavailable; copied full page as preview.');
    return is_file($dstPng) ? 0 : -1;
  }

  private function tryCliCropWithImageMagick(string $src, string $dst, int $x, int $y, int $cw, int $ch): bool
  {
    $cmd = null;
    if ($magick = $this->findBinary('magick')) {
      $cmd = [$magick, 'convert', $src, '-crop', "{$cw}x{$ch}+{$x}+{$y}", '-bordercolor', 'white', '-border', '12x12', $dst];
    } elseif ($convert = $this->findBinary('convert')) {
      $cmd = [$convert, $src, '-crop', "{$cw}x{$ch}+{$x}+{$y}", '-bordercolor', 'white', '-border', '12x12', $dst];
    }
    if ($cmd) {
      try { $this->runProcess($cmd, 60); return is_file($dst); }
      catch (\Throwable $e) { Log::warning('ImageMagick CLI crop failed', ['err' => $e->getMessage()]); }
    }
    return false;
  }

  /* ────────────────────── process + binary discovery ────────────────────── */

  private function runProcess(array $cmd, int $timeoutSeconds = 90): string
  {
    $p = new Process($cmd);
    $p->setTimeout($timeoutSeconds);
    $p->run();
    Log::debug('Process', ['cmd' => implode(' ', $cmd), 'exit' => $p->getExitCode()]);
    if (!$p->isSuccessful()) throw new ProcessFailedException($p);
    return $p->getOutput() . $p->getErrorOutput();
  }

  private function findBinary(string $name): ?string
  {
    $isWin = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';

    // which/where
    try {
      $p = new Process($isWin ? ['where', $name] : ['which', $name]);
      $p->setTimeout(4);
      $p->run();
      if ($p->isSuccessful()) {
        $out = trim($p->getOutput());
        if ($out !== '') {
          $first = preg_split('/\r\n|\r|\n/', $out)[0];
          if ($first && is_file($first)) return $first;
        }
      }
    } catch (\Throwable $e) {}

    // Hostinger Xpdf path
    $xpdfDir = '/home/u780655614/opt/xpdf-tools-linux-4.05/bin64';
    $cand = $xpdfDir . DIRECTORY_SEPARATOR . $name;
    if (is_file($cand)) return $cand;

    // Windows fallbacks
    if ($isWin) {
      foreach ([
        'C:\Program Files\ImageMagick-7*\magick.exe',
        'C:\Program Files\ImageMagick-6*\convert.exe',
        'C:\Program Files\poppler-*\bin\\' . $name . '.exe',
        'C:\poppler\bin\\' . $name . '.exe',
      ] as $pattern) {
        foreach (glob($pattern) ?: [] as $match) if (is_file($match)) return $match;
      }
    }

    // Env override
    foreach (['XPDF_BIN_DIR', 'POPPLER_BIN_DIR'] as $envKey) {
      $dir = env($envKey) ?: config('services.poppler.bin_dir', null);
      if ($dir) {
        $p = rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $name . ($isWin ? '.exe' : '');
        if (is_file($p)) return $p;
      }
    }
    return null;
  }
}
