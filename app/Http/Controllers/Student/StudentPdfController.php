<?php
// app/Http/Controllers/Student/StudentPdfController.php
namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use setasign\Fpdi\Fpdi;

class StudentPdfController extends Controller
{
    /**
     * Strict 2-page output:
     *   - Page 1: template page 1
     *   - Page 2: template page 2 + overlays of COR (top box) and COG (bottom box)
     *
     * Tries request('cor_png_path') / request('cog_png_path') first (absolute path or /storage URL).
     * Falls back to storage/app/cor/cor_upload.png and storage/app/cog/cog_upload.png.
     * If a provided path is a PDF, will convert page 1 → PNG (Imagick) before placing.
     */
    public function generate(Request $request)
    {
        // Prefer the 2025 template; fall back to CLEAN
        $templateAbs = storage_path("app/pdf_templates/Dean's List Application Form 2025.pdf");
        if (!file_exists($templateAbs)) {
            $templateAbs = storage_path("app/pdf_templates/Dean's List Application Form CLEAN.pdf");
        }

        $outRel = 'pdf_output/filled_dean_form.pdf';
        $outAbs = storage_path('app/public/' . $outRel);
        File::ensureDirectoryExists(dirname($outAbs));

        // --- Inputs (may be absolute paths or /storage/... urls)
        $reqCor = trim((string) $request->input('cor_png_path', ''));
        $reqCog = trim((string) $request->input('cog_png_path', ''));

        // --- Fallbacks written by your upload pipeline
        $fallbackCor = storage_path('app/cor/cor_upload.png');
        $fallbackCog = storage_path('app/cog/cog_upload.png');

        // Resolve to usable image paths (handles URL → absolute, and PDF → PNG)
        $corImg = $this->resolveOverlayImagePath(
            $this->maybeUrlToAbs($reqCor),
            $fallbackCor,
            'COR'
        );
        $cogImg = $this->resolveOverlayImagePath(
            $this->maybeUrlToAbs($reqCog),
            $fallbackCog,
            'COG'
        );

        try {
            if (!file_exists($templateAbs)) {
                Log::error('[PDF] Template not found', ['template' => $templateAbs]);
                return response()->json([
                    'ok' => false,
                    'message' => 'Template not found',
                    'public_url' => null
                ], 200);
            }

            $pdf = new Fpdi();
            $pageCount = $pdf->setSourceFile($templateAbs);

            // ---- Page 1
            $tpl1  = $pdf->importPage(1);
            $size1 = $pdf->getTemplateSize($tpl1);
            $pdf->AddPage($size1['orientation'], [$size1['width'], $size1['height']]);
            $pdf->useTemplate($tpl1);

            // ---- Page 2 + overlays
            if ($pageCount >= 2) {
                $tpl2  = $pdf->importPage(2);
                $size2 = $pdf->getTemplateSize($tpl2);
                $pdf->AddPage($size2['orientation'], [$size2['width'], $size2['height']]);
                $pdf->useTemplate($tpl2);

                // Boxes in mm (adjust if needed)
                $COR_BOX = ['x' => 15.0, 'y' => 105.0, 'w' => 180.0, 'h' => 78.0];
                $COG_BOX = ['x' => 15.0, 'y' => 190.0, 'w' => 180.0, 'h' => 86.0];

                // Contain-fit placement
                $placeContain = function (Fpdi $pdf, string $imgPath, array $box) {
                    [$wmm, $hmm] = $this->imageSizeMm($imgPath);
                    if ($wmm <= 0 || $hmm <= 0) return;

                    $ratio = min($box['w'] / $wmm, $box['h'] / $hmm);
                    $w = $wmm * $ratio;
                    $h = $hmm * $ratio;
                    $x = $box['x'] + ($box['w'] - $w) / 2;
                    $y = $box['y'] + ($box['h'] - $h) / 2;

                    // Normalize path slashes for FPDI
                    $path = str_replace('\\', '/', $imgPath);
                    $pdf->Image($path, $x, $y, $w, $h);
                };

                if ($corImg && file_exists($corImg)) {
                    $placeContain($pdf, $corImg, $COR_BOX);
                } else {
                    Log::warning('[PDF] Missing COR overlay', ['path' => $corImg ?: '(none)']);
                }

                if ($cogImg && file_exists($cogImg)) {
                    $placeContain($pdf, $cogImg, $COG_BOX);
                } else {
                    Log::warning('[PDF] Missing COG overlay', ['path' => $cogImg ?: '(none)']);
                }
            }

            // Save (NO extra pages appended)
            $pdf->Output($outAbs, 'F');

            $url = Storage::disk('public')->url($outRel) ?: asset('storage/' . $outRel);

            return response()->json([
                'ok' => true,
                'public_url' => $url,
                'path' => $outAbs,
                'used' => [
                    'template'  => $templateAbs,
                    'cor_image' => $corImg,
                    'cog_image' => $cogImg,
                ],
            ], 200);
        } catch (\Throwable $e) {
            Log::error('[PDF] Build failed', ['err' => $e->getMessage()]);
            return response()->json([
                'ok'      => false,
                'message' => 'PDF build failed',
                'error'   => $e->getMessage(),
            ], 200);
        }
    }

    /**
     * If $maybe is a public /storage/... URL, convert to an absolute path.
     * Otherwise return input unchanged.
     */
    private function maybeUrlToAbs(?string $maybe): ?string
    {
        if (!$maybe) return null;
        $p = trim($maybe, " \t\n\r\0\x0B\"'");
        if ($p === '') return null;

        // Already absolute path?
        if (preg_match('/^[A-Za-z]:[\\\\\\/]/', $p) || str_starts_with($p, '/')) {
            return $p;
        }

        // Public URLs like /storage/cog/cog_upload.png
        if (preg_match('#/storage/(.+)$#', $p, $m)) {
            $rel = $m[1]; // e.g. cog/cog_upload.png
            return storage_path('app/public/' . $rel);
        }

        return $p;
    }

    /**
     * Decide which path to use for an overlay:
     * 1) Try provided path (if exists).
     *    - If it’s a PDF, try to convert page 1 → PNG (Imagick).
     * 2) Else fall back to provided fallback PNG (if exists).
     */
    private function resolveOverlayImagePath(?string $maybePath, string $fallbackPng, string $label): ?string
    {
        $p = $maybePath ? trim($maybePath) : '';
        if ($p && file_exists($p)) {
            if (preg_match('/\.pdf$/i', $p)) {
                $png = $this->tryPdfFirstPageToPng($p, $label);
                if ($png && file_exists($png)) return $png;
                Log::warning("[PDF] {$label}: Provided PDF could not be converted, using fallback.", ['path' => $p]);
            } else {
                return $p; // already an image
            }
        }

        if (file_exists($fallbackPng)) return $fallbackPng;
        return null;
    }

    /**
     * Convert first page of a PDF to a temporary PNG using Imagick (if available).
     * Returns PNG path or null.
     */
    private function tryPdfFirstPageToPng(string $pdfPath, string $label): ?string
    {
        if (!extension_loaded('imagick')) {
            Log::warning("[PDF] {$label}: Imagick not available for PDF→PNG conversion.");
            return null;
        }

        try {
            File::ensureDirectoryExists(storage_path('app/tmp_pdf_imgs'));
            $out = storage_path('app/tmp_pdf_imgs/' . $label . '_overlay_' . uniqid() . '.png');

            $im = new \Imagick();
            $im->setResolution(200, 200);
            $im->readImage($pdfPath . '[0]');                  // only first page
            if (method_exists($im, 'autoOrient')) $im->autoOrient();
            if (method_exists($im, 'setImageAlphaChannel')) {
                $im->setImageAlphaChannel(\Imagick::ALPHACHANNEL_REMOVE);
            }
            $im->setImageBackgroundColor('white');
            $im->setImageFormat('png');
            $im->setImageCompressionQuality(95);

            // Cap width so overlay stays light
            $w = $im->getImageWidth();
            if ($w > 2400) {
                $scale = 2400 / $w;
                $im->resizeImage(
                    (int)($im->getImageWidth() * $scale),
                    (int)($im->getImageHeight() * $scale),
                    \Imagick::FILTER_LANCZOS,
                    1
                );
            }

            $im->writeImage($out);
            $im->clear();
            $im->destroy();

            return file_exists($out) ? $out : null;
        } catch (\Throwable $e) {
            Log::warning("[PDF] {$label}: PDF→PNG convert failed", ['err' => $e->getMessage()]);
            return null;
        }
    }

    /** Image size → mm (EXIF-aware DPI, with safe defaults). */
    private function imageSizeMm(string $path): array
    {
        try {
            $info = @getimagesize($path);
            $pxW  = $info[0] ?? 0;
            $pxH  = $info[1] ?? 0;
            if (!$pxW || !$pxH) return [0, 0];

            // Default DPI
            $dpi = 96.0;

            // EXIF DPI, when available
            if (function_exists('exif_read_data')) {
                $exif = @exif_read_data($path);
                if ($exif && isset($exif['XResolution'])) {
                    $x = (float) $exif['XResolution'];
                    $unit = $exif['ResolutionUnit'] ?? 2; // 2=inches, 3=cm
                    if ($x > 0) $dpi = ($unit == 3) ? $x * 2.54 : $x;
                }
            }

            // Some libs populate $info['dpi'][0]
            if (isset($info['dpi']) && is_array($info['dpi']) && $info['dpi'][0] > 0) {
                $dpi = (float) $info['dpi'][0];
            }

            $mmPerIn = 25.4;
            return [
                ($pxW / max(1.0, $dpi)) * $mmPerIn,
                ($pxH / max(1.0, $dpi)) * $mmPerIn
            ];
        } catch (\Throwable $e) {
            return [0, 0];
        }
    }
}
