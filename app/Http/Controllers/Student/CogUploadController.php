<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class CogUploadController extends Controller
{
    /**
     * Accepts either:
     *  - pdf: application/pdf (preferred)
     *  - image: image/* (fallback)
     *
     * Returns JSON keys the Blade expects:
     *   ok, message, cog_image_path, cog_image_url, public_url, cog_pdf_url, cog_pdf_path, ocr_text, pdf_text
     */
    public function upload(Request $request)
    {
        @set_time_limit(300);

        $hasPdf   = $request->hasFile('pdf');
        $hasImage = $request->hasFile('image');

        if (!$hasPdf && !$hasImage) {
            return response()->json(['ok' => false, 'error' => 'No file uploaded.'], 422);
        }

        if ($hasPdf) {
            $request->validate(['pdf' => 'required|file|mimes:pdf|max:25600']);
            return $this->handlePdf($request->file('pdf'));
        }

        $request->validate(['image' => 'required|file|image|max:15360']);
        return $this->handleImage($request->file('image'));
    }

    private function handlePdf($uploadedPdf)
    {
        $cogDir = storage_path('app/cog');
        $pubDir = storage_path('app/public/uploads/cog');
        File::ensureDirectoryExists($cogDir);
        File::ensureDirectoryExists($pubDir);

        $stamp    = now()->format('Ymd_His') . '_' . Str::random(5);
        $debugPdf = $cogDir . DIRECTORY_SEPARATOR . "cog_{$stamp}.pdf";
        $fixedPdf = $cogDir . DIRECTORY_SEPARATOR . 'cog_upload.pdf';

        // Save both a timestamped copy and a canonical copy
        $uploadedPdf->move($cogDir, basename($debugPdf));
        @copy($debugPdf, $fixedPdf);

        // Mirror PDF to public
        $publicPdfAbs = $pubDir . DIRECTORY_SEPARATOR . 'cog_upload.pdf';
        @copy($fixedPdf, $publicPdfAbs);
        $publicPdfUrl = asset('storage/uploads/cog/cog_upload.pdf');

        // Try to render first page to PNG for a nicer preview
        $tmpPng   = $cogDir . DIRECTORY_SEPARATOR . "cog_first_{$stamp}.png";
        $finalPng = $cogDir . DIRECTORY_SEPARATOR . 'cog_upload.png';
        $rendered = $this->renderFirstPageToPng($fixedPdf, $tmpPng);

        $pngPublicUrl = null;
        $message = 'Saved (PDF ready; PNG preview unavailable).';

        if ($rendered) {
            $cropStatus = $this->cropCogRegion($tmpPng, $finalPng); // 1=cropped, 0=copied full, -1=fail

            if ($cropStatus >= 0 && is_file($finalPng)) {
                $publicPngAbs = $pubDir . DIRECTORY_SEPARATOR . 'cog_upload.png';
                @copy($finalPng, $publicPngAbs);
                $pngPublicUrl = asset('storage/uploads/cog/cog_upload.png');

                $message = match ($cropStatus) {
                    1 => 'Saved with cropped PNG preview.',
                    0 => 'Saved with full-page PNG preview (cropping unavailable).',
                    default => 'Saved (PDF ready; PNG preview unavailable).',
                };
            }
        }

        if (is_file($tmpPng)) @unlink($tmpPng);

        // Extract verbatim text from PDF (for OCR section)
        $ocrText = $this->pdfToText($fixedPdf); // may be empty if poppler not found

        Log::info('COG upload (PDF)', [
            'pdf_public_exists' => is_file($publicPdfAbs),
            'png_public_url'    => $pngPublicUrl,
        ]);

        return response()->json([
            'ok'              => true,
            'message'         => $message,
            'cog_image_path'  => $pngPublicUrl ? $finalPng : null,
            'cog_image_url'   => $pngPublicUrl,                       // preferred preview
            'public_url'      => $pngPublicUrl ?: $publicPdfUrl,      // fallback preview (PDF)
            'cog_pdf_url'     => $publicPdfUrl,                       // FE can render this with pdf.js
            'cog_pdf_path'    => $fixedPdf,
            'ocr_text'        => $ocrText,                            // server verbatim text
            'pdf_text'        => $ocrText,                            // alias for FE
        ]);
    }

    private function handleImage($uploadedImage)
    {
        $cogDir = storage_path('app/cog');
        $pubDir = storage_path('app/public/uploads/cog');
        File::ensureDirectoryExists($cogDir);
        File::ensureDirectoryExists($pubDir);

        $stamp    = now()->format('Ymd_His') . '_' . Str::random(5);
        $ext      = strtolower($uploadedImage->getClientOriginalExtension() ?: 'png');

        $fixedPng = $cogDir . DIRECTORY_SEPARATOR . 'cog_upload.png';
        $debugPng = $cogDir . DIRECTORY_SEPARATOR . "cog_{$stamp}.{$ext}";
        $uploadedImage->move($cogDir, basename($debugPng));
        @copy($debugPng, $fixedPng);

        // Light, safe post-processing
        $this->tryTrimWhite($fixedPng);

        $publicPngAbs = $pubDir . DIRECTORY_SEPARATOR . 'cog_upload.png';
        @copy($fixedPng, $publicPngAbs);
        $pngPublicUrl = asset('storage/uploads/cog/cog_upload.png');

        Log::info('COG upload (image)', ['png_public_exists' => is_file($publicPngAbs)]);

        return response()->json([
            'ok'             => true,
            'message'        => 'Saved image preview.',
            'cog_image_path' => $fixedPng,
            'cog_image_url'  => $pngPublicUrl,
            'public_url'     => $pngPublicUrl,
            'ocr_text'       => '', // images don’t need PDF text extraction
            'pdf_text'       => '',
        ]);
    }

    /* ========================= Rendering ========================= */

    private function renderFirstPageToPng(string $pdfAbs, string $outPngAbs): bool
    {
        // Prefer Poppler (pdftoppm)
        if ($bin = $this->findBinary('pdftoppm')) {
            try {
                $noExt = $outPngAbs . '.page1';
                $this->runProcess([$bin, '-png', '-singlefile', '-r', '300', $pdfAbs, $noExt], 120);
                $cand = $noExt . '.png';
                if (is_file($cand)) {
                    @rename($cand, $outPngAbs);
                    return true;
                }
            } catch (\Throwable $e) {
                Log::warning('pdftoppm failed', ['err' => $e->getMessage()]);
            }
        }

        // Fallback: Imagick
        if (extension_loaded('imagick')) {
            try {
                $img = new \Imagick();
                $img->setResolution(300, 300);
                $img->readImage($pdfAbs . '[0]');
                $img->setImageBackgroundColor('white');
                $img->setImageAlphaChannel(\Imagick::ALPHACHANNEL_REMOVE);
                $img = $img->mergeImageLayers(\Imagick::LAYERMETHOD_FLATTEN);
                $img->setImageFormat('png');
                $img->writeImage($outPngAbs);
                $img->clear(); $img->destroy();
                return is_file($outPngAbs);
            } catch (\Throwable $e) {
                Log::warning('Imagick render failed', ['err' => $e->getMessage()]);
            }
        }

        return false;
    }

    /**
     * Crop the rendered PNG to the COG header + table band.
     * Tune via .env:
     *   COG_CROP_LEFT=0.03
     *   COG_CROP_RIGHT=0.97
     *   COG_CROP_TOP=0.12
     *   COG_CROP_BOTTOM=0.92
     *
     * Returns: 1=cropped, 0=copied full page, -1=failure
     */
    private function cropCogRegion(string $srcPng, string $dstPng): int
    {
        $left   = (float) env('COG_CROP_LEFT',   0.05);
        $right  = (float) env('COG_CROP_RIGHT',  0.96);
        $top    = (float) env('COG_CROP_TOP',    0.04);
        $bottom = (float) env('COG_CROP_BOTTOM', 0.495);

        // safety clamps
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

        // 1) Imagick
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

        // 2) GD fallback
        if (function_exists('imagecreatefrompng')) {
            try {
                $src = imagecreatefrompng($srcPng);
                if (!$src) return -1;
                $dst = imagecreatetruecolor($cw, $ch);
                $white = imagecolorallocate($dst, 255,255,255);
                imagefill($dst, 0, 0, $white);
                imagecopy($dst, $src, 0, 0, $x, $y, $cw, $ch);

                // small white border
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

        // 3) CLI (ImageMagick) fallback
        if ($this->tryCliCropWithImageMagick($srcPng, $dstPng, $x, $y, $cw, $ch)) {
            return 1;
        }

        // 4) As-is
        @copy($srcPng, $dstPng);
        Log::warning('COG cropping unavailable; copied full page as preview.');
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
            try {
                $this->runProcess($cmd, 60);
                return is_file($dst);
            } catch (\Throwable $e) {
                Log::warning('ImageMagick CLI crop failed', ['err' => $e->getMessage()]);
            }
        }
        return false;
    }

    /* ========================= Utilities ========================= */

    private function runProcess(array $cmd, int $timeoutSeconds = 90): string
    {
        $p = new Process($cmd);
        $p->setTimeout($timeoutSeconds);
        $p->run();
        Log::debug('Process', ['cmd' => implode(' ', $cmd), 'exit' => $p->getExitCode()]);
        if (!$p->isSuccessful()) {
            throw new ProcessFailedException($p);
        }
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

        if ($isWin) {
            $candidates = [
                // Poppler
                'C:\Program Files\poppler-24.08.0\Library\bin\\' . $name . '.exe',
                'C:\Program Files\poppler-24.07.0\Library\bin\\' . $name . '.exe',
                'C:\Program Files\poppler-24.06.0\Library\bin\\' . $name . '.exe',
                'C:\poppler\bin\\' . $name . '.exe',
                // ImageMagick v7
                'C:\Program Files\ImageMagick-7*\magick.exe',
                // ImageMagick v6
                'C:\Program Files\ImageMagick-6*\convert.exe',
            ];
            foreach ($candidates as $c) {
                foreach (glob($c) ?: [] as $match) {
                    if (is_file($match)) return $match;
                }
                if (is_file($c)) return $c;
            }
        }

        $dir = env('POPPLER_BIN_DIR') ?: config('services.poppler.bin_dir', null);
        if ($dir) {
            $p = rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $name . ($isWin ? '.exe' : '');
            if (is_file($p)) return $p;
        }

        return null;
    }

    private function tryTrimWhite(string $png): void
    {
        if (!extension_loaded('imagick') || !is_file($png)) return;
        try {
            $im = new \Imagick($png);
            $im->setImageBackgroundColor('white');
            $im->trimImage(2);
            $im->setImagePage(0,0,0,0);
            $im->writeImage($png);
            $im->clear(); $im->destroy();
        } catch (\Throwable $e) {
            Log::warning('trimWhite failed', ['err' => $e->getMessage()]);
        }
    }

    /**
     * Verbatim PDF text (for OCR block) using Poppler's pdftotext.
     * Returns '' if Poppler is not available.
     */
    private function pdfToText(string $pdfPath): string
    {
        if (!is_file($pdfPath)) return '';
        $bin = env('PDFTOTEXT_PATH') ?: $this->findBinary('pdftotext');

        Log::info('pdfToText binary', ['bin' => $bin, 'exists' => $bin && file_exists($bin)]);

        if (!$bin || !file_exists($bin)) {
            Log::warning('pdftotext not found; skipping OCR text');
            return '';
        }

        try {
            $out = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'txt_' . uniqid() . '.txt';
            $this->runProcess([$bin, '-layout', '-nopgbrk', $pdfPath, $out], 90);

            $text = is_file($out) ? (string) @file_get_contents($out) : '';
            @unlink($out);
            return trim(str_replace("\r", "", $text));
        } catch (\Throwable $e) {
            Log::warning('pdfToText failed', ['err' => $e->getMessage()]);
            return '';
        }
    }
}
