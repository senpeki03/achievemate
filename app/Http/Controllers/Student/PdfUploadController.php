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
    /**
     * Step 2 — COR upload:
     * - Save as storage/app/cor/cor_upload.pdf (fixed name)
     * - Render page 1 to PNG (Poppler/Imagick)
     * - Try to crop center band; if unavailable, keep full page
     * - Mirror files to storage/app/public/uploads/cor for preview
     */
    public function upload(Request $request)
    {
        @set_time_limit(300);

        $request->validate([
            'pdf' => 'required|file|mimes:pdf|max:25600', // 25 MB
        ]);

        // Directories
        $corDir = storage_path('app/cor');
        $pubDir = storage_path('app/public/uploads/cor');
        File::ensureDirectoryExists($corDir);
        File::ensureDirectoryExists($pubDir);

        // 1) Save timestamped copy + the fixed canonical filename
        $stamp    = now()->format('Ymd_His') . '_' . Str::random(5);
        $debugPdf = $corDir . DIRECTORY_SEPARATOR . "cor_{$stamp}.pdf";
        $fixedPdf = $corDir . DIRECTORY_SEPARATOR . 'cor_upload.pdf'; // canonical
        $request->file('pdf')->move($corDir, basename($debugPdf));
        @copy($debugPdf, $fixedPdf); // refresh fixed copy

        // Mirror the PDF to /public for iframe preview
        $publicPdfAbs = $pubDir . DIRECTORY_SEPARATOR . 'cor_upload.pdf';
        @copy($fixedPdf, $publicPdfAbs);
        $publicPdfUrl = asset('storage/uploads/cor/cor_upload.pdf');

        // 2) Try rendering page 1 to PNG
        $tmpPng   = $corDir . DIRECTORY_SEPARATOR . "cor_first_{$stamp}.png"; // temp (full page)
        $finalPng = $corDir . DIRECTORY_SEPARATOR . 'cor_upload.png';         // (maybe cropped)
        $rendered = $this->renderFirstPageToPng($fixedPdf, $tmpPng);

        // 3) Crop the rendered PNG to a center band (or fallback)
        $pngPublicUrl = null;
        $msg = 'Saved (PDF ready; PNG preview unavailable).';

        if ($rendered) {
            $cropStatus = $this->cropCorRegion($tmpPng, $finalPng); // 1=cropped, 0=copied full, -1=fail

            if ($cropStatus >= 0) {
                $publicPngAbs = $pubDir . DIRECTORY_SEPARATOR . 'cor_upload.png';
                @copy($finalPng, $publicPngAbs);
                $pngPublicUrl = asset('storage/uploads/cor/cor_upload.png');
                $msg = match ($cropStatus) {
                    1 => 'Saved with cropped PNG preview.',
                    0 => 'Saved with full-page PNG preview (cropping unavailable).',
                    default => 'Saved (PDF ready; PNG preview unavailable).',
                };
            }
        }

        if (is_file($tmpPng)) @unlink($tmpPng);

        // Bonus diagnostics (helps you verify storage link + files exist)
        Log::info('COR upload done', [
            'pdf_public_exists' => is_file($publicPdfAbs),
            'png_public_exists' => isset($publicPngAbs) ? is_file($publicPngAbs) : false,
        ]);

        return response()->json([
            'ok'                   => true,
            'message'              => $msg,
            'cor_pdf_path'         => $fixedPdf,
            'cor_pdf_url'          => $publicPdfUrl,
            'cor_upload_png_path'  => $pngPublicUrl ? $finalPng : null,
            'cor_upload_png_url'   => $pngPublicUrl,
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
                $img->readImage($pdfAbs . '[0]'); // first page
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
     * Crop the rendered PNG to the central band.
     * Returns: 1=cropped, 0=copied full page (no crop engine), -1=failure
     *
     * Tunable via .env (defaults match your screenshot closely):
     *   COR_CROP_LEFT=0.04   (4% from left)
     *   COR_CROP_RIGHT=0.965 (3.5% from right)
     *   COR_CROP_TOP=0.17    (17% from top)
     *   COR_CROP_BOTTOM=0.80 (80% of height)
     */
    private function cropCorRegion(string $srcPng, string $dstPng): int
    {
        $left   = (float) env('COR_CROP_LEFT',   0.00);
        $right  = (float) env('COR_CROP_RIGHT',  0.970);
        $top    = (float) env('COR_CROP_TOP',    0.04);
        $bottom = (float) env('COR_CROP_BOTTOM', 0.52);

        // Clamp for safety
        $left   = max(0.0,  min($left,  0.49));
        $right  = max(0.51, min($right, 1.0));
        $top    = max(0.0,  min($top,   0.49));
        $bottom = max(0.51, min($bottom,1.0));

        // Dimensions (works without GD)
        try {
            [$w, $h] = getimagesize($srcPng);
        } catch (\Throwable $e) {
            Log::warning('getimagesize failed', ['err' => $e->getMessage()]);
            return -1;
        }

        $x  = (int) floor($left   * $w);
        $y  = (int) floor($top    * $h);
        $cw = (int) ceil(($right  - $left)  * $w);
        $ch = (int) ceil(($bottom - $top)   * $h);

        // 1) Prefer Imagick extension
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

                // Add small border
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

        // 3) ImageMagick CLI (magick/convert) if present
        if ($this->tryCliCropWithImageMagick($srcPng, $dstPng, $x, $y, $cw, $ch)) {
            return 1;
        }

        // 4) Last resort: no crop, just copy so preview still works
        @copy($srcPng, $dstPng);
        Log::warning('Cropping unavailable; copied full page as preview.');
        return is_file($dstPng) ? 0 : -1;
    }

    private function tryCliCropWithImageMagick(string $src, string $dst, int $x, int $y, int $cw, int $ch): bool
    {
        $cmd = null;

        if ($magick = $this->findBinary('magick')) {
            // IM v7: magick convert src -crop WxH+X+Y -bordercolor white -border 12x12 dst
            $cmd = [$magick, 'convert', $src, '-crop', "{$cw}x{$ch}+{$x}+{$y}", '-bordercolor', 'white', '-border', '12x12', $dst];
        } elseif ($convert = $this->findBinary('convert')) {
            // IM v6 (warning: Windows has a system convert.exe; findBinary() handles common IM paths)
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

    /**
     * Locate binaries (cross-platform), with Windows fallbacks and env override.
     * Set POPPLER_BIN_DIR=/absolute/path/to/… in production if needed.
     */
    private function findBinary(string $name): ?string
    {
        $isWin = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';

        // where/which first
        try {
            $p = new Process($isWin ? ['where', $name] : ['which', $name]);
            $p->setTimeout(4);
            $p->run();
            if ($p->isSuccessful()) {
                $out = trim($p->getOutput());
                if ($out !== '') {
                    $first = preg_split('/\r\n|\r|\n/', $out)[0];
                    if ($first) return $first;
                }
            }
        } catch (\Throwable $e) {}

        // Common Windows installs (support wildcards)
        if ($isWin) {
            $candidates = [
                // Poppler
                'C:\Program Files\poppler-24.08.0\Library\bin\\' . $name . '.exe',
                'C:\Program Files\poppler-24.07.0\Library\bin\\' . $name . '.exe',
                'C:\Program Files\poppler-24.06.0\Library\bin\\' . $name . '.exe',
                'C:\Program Files\poppler-*\bin\\' . $name . '.exe',
                'C:\poppler\bin\\' . $name . '.exe',
                // ImageMagick v7
                'C:\Program Files\ImageMagick-7*\magick.exe',
                // ImageMagick v6
                'C:\Program Files\ImageMagick-6*\convert.exe',
            ];
            foreach ($candidates as $c) {
                foreach (glob($c) ?: [] as $match) {
                    if (is_file($match) && (str_ends_with(strtolower($name), 'exe') || pathinfo($match, PATHINFO_EXTENSION) === 'exe' || true)) {
                        return $match;
                    }
                }
                if (is_file($c)) return $c;
            }
        }

        // Env / config override
        $dir = env('POPPLER_BIN_DIR') ?: config('services.poppler.bin_dir', null);
        if ($dir) {
            $p = rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $name . ($isWin ? '.exe' : '');
            if (is_file($p)) return $p;
        }

        return null;
    }
}
