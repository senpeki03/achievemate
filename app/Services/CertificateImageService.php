<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Typography\FontFactory;
use App\Models\Application;

class CertificateImageService
{
    private ImageManager $im;

    public function __construct()
    {
        $this->im = new ImageManager(new Driver());
    }

    public function makeDeansCertPng(array $data): ?string
    {
        try {
            // ---------- Template ----------
            $template = $data['template_png'] ?? null;
            if (!$template || !is_file($template)) {
                Log::warning('Cert: template missing', ['template' => $template]);
                return null;
            }

            $img = $this->im->read($template);
            $W = $img->width();
            $H = $img->height();

            // ---------- Auto-enrich missing fields (Program/Sem/AY) ----------
            try {
                $needsProgram  = empty($data['program']) && empty($data['degree_line']);
                $needsAy       = empty($data['school_year']) || $data['school_year'] === '—';
                $needsSemester = empty($data['semester']);

                if ($needsProgram || $needsAy || $needsSemester) {
                    $studentId = isset($data['student_id']) ? (int)$data['student_id'] : null;
                    if (!$studentId && !empty($data['app_id'])) {
                        $studentId = (int) Application::where('Application_id', $data['app_id'])->value('Student_id');
                    }
                    if ($studentId) {
                        $builderData = app(\App\Services\DeanCertDataBuilder::class)->buildFromStudent($studentId, [
                            'template_png' => $data['template_png'] ?? null,
                            'out_rel'      => $data['out_rel']      ?? null,
                            'app_id'       => $data['app_id']       ?? null,
                        ]);
                        foreach (['program','degree_line','semester','school_year'] as $k) {
                            if (!isset($data[$k]) || $data[$k] === '' || ($k === 'school_year' && $data[$k] === '—')) {
                                if (isset($builderData[$k])) $data[$k] = $builderData[$k];
                            }
                        }
                        if (empty($data['gwa']) && isset($builderData['gwa'])) {
                            $data['gwa'] = $builderData['gwa'];
                        }
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('Cert: auto-enrich failed', ['err' => $e->getMessage()]);
            }

            // ---------- Inputs ----------
            $appId          = (string) ($data['app_id'] ?? 'X');
            $studentNameRaw = trim((string)($data['student_name'] ?? 'STUDENT NAME'));
            $studentName    = $this->nameWithMiddleInitial($studentNameRaw);

            // program: use degree_line if program is empty
            $programRaw     = trim((string) ($data['program'] ?? ''));
            $degreeLineRaw  = trim((string) ($data['degree_line'] ?? ''));
            $program        = $programRaw !== '' ? $programRaw : $degreeLineRaw;

            $gwaStr         = trim((string) ($data['gwa'] ?? ''));
            $semester       = trim((string) ($data['semester'] ?? 'First Semester'));
            $schoolYear     = trim((string) ($data['school_year'] ?? '—'));
            $dateConfer     = trim((string) ($data['date_conferred'] ?? date('F d, Y')));

            $honorTierIn = isset($data['honor_tier']) ? trim((string)$data['honor_tier']) : null;
            $deanName    = trim((string)($data['dean_name']  ?? 'Prof. LORISSA JOANA E. BUENAS, DTech'));
            $deanTitle   = trim((string)($data['dean_title'] ?? 'Dean, College of Informatics and Computing Sciences'));
            $showSig     = array_key_exists('show_signature', $data) ? (bool)$data['show_signature'] : true;

            // Parse GWA
            $gwa = null;
            if ($gwaStr !== '') {
                $n = floatval(preg_replace('/[^0-9.]/', '', $gwaStr));
                $gwa = $n > 0 ? $n : null;
            }

            // ---------- Fonts ----------
            $envRegular = env('CERT_FONT_REGULAR');
            $envBold    = env('CERT_FONT_BOLD');

            $fontRegular = $this->firstFont(array_filter([
                $envRegular,
                'C:/Windows/Fonts/arial.ttf',
                'C:/Windows/Fonts/calibri.ttf',
                '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
                resource_path('fonts/Inter-Regular.ttf'),
                resource_path('fonts/Poppins-Regular.ttf'),
                resource_path('fonts/Montserrat-Regular.ttf'),
            ]));

            $fontBold = $this->firstFont(array_filter([
                $envBold,
                'C:/Windows/Fonts/arialbd.ttf',
                'C:/Windows/Fonts/calibrib.ttf',
                '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
                resource_path('fonts/Inter-Bold.ttf'),
                resource_path('fonts/Poppins-Bold.ttf'),
                resource_path('fonts/Montserrat-Bold.ttf'),
            ]));

            $fontItalic = $this->firstFont([
                'C:/Windows/Fonts/ariali.ttf',
                '/usr/share/fonts/truetype/dejavu/DejaVuSans-Oblique.ttf',
                resource_path('fonts/Inter-Italic.ttf'),
                resource_path('fonts/Poppins-Italic.ttf'),
                resource_path('fonts/Montserrat-Italic.ttf'),
            ]) ?: $fontRegular;

            // ---------- Colors ----------
            $ink       = '#202327';
            $muted     = '#202327';
            $accentRed = '#d61f26';
            $shadow    = 'rgba(0,0,0,0.35)';

            // ---------- Layout ----------
            $panelLeftPct  = 0.450;
            $xFineShiftPx  = -12;

            $X = (int) round($panelLeftPct * $W) + $xFineShiftPx;

            $yTop      = (int) round(0.235 * $H);
            $yName     = $yTop + (int) round(0.035 * $H);
            $yProgram  = $yName + (int) round(0.05 * $H);
            $yParaTop  = $yProgram + (int) round(0.055 * $H);
            $yDate     = $yParaTop + (int) round(0.22 * $H);
            $yDeanName = $yDate + (int) round(0.13 * $H);
            $yDeanTit  = $yDeanName + (int) round(0.038 * $H);

            // ↓↓↓ Smaller fonts (one more step down) to match your sample cert
            $fsName     = max(28, (int) round($H * 0.048)); // big red name
            $fsProgram  = max(15, (int) round($H * 0.024)); // italic program
            $fsPara     = max(15, (int) round($H * 0.024)); // paragraph lines
            $fsDate     = max(15, (int) round($H * 0.026)); // date
            $fsDeanName = max(17, (int) round($H * 0.028)); // dean name
            $fsDeanTit  = max(16, (int) round($H * 0.022)); // dean title

            // Extra gap between Name and Program (more spacing)
            $nameProgramExtraGapPx = max(12, (int) round($H * 0.018));
            $yProgram += $nameProgramExtraGapPx;

            // Push the paragraph block a bit further down
            $paraExtraDownPx = (int) round($H * 0.012);  // ~1.2% of page height
            $yParaTop += $paraExtraDownPx;


            // Autoscale BIG name (rough char-based heuristic)
            $lenName = mb_strlen($studentName);
            if     ($lenName > 42) $fsName = max(36, $fsName - 10);
            elseif ($lenName > 34) $fsName = max(40, $fsName - 6);
            elseif ($lenName > 28) $fsName = max(44, $fsName - 2);

            // Long program → shrink a bit
            if (mb_strlen($program) > 60) {
                $fsProgram = (int) max(18, $fsProgram - 6);
            }

            $downShiftPx = 30;
            $yTop      += $downShiftPx;
            $yName     += $downShiftPx;
            $yProgram  += $downShiftPx;
            $yParaTop  += $downShiftPx;
            $yDate     += $downShiftPx;
            $yDeanName += $downShiftPx;
            $yDeanTit  += $downShiftPx;

            // ---------- Paragraph content ----------
            $ay = $this->normalizeAy($schoolYear);

            $rank = $honorTierIn !== null && $honorTierIn !== ''
                ? $honorTierIn
                : ($this->honorsFromGwa($gwa) ?: 'Honors');

            $line1 = "In recognition of outstanding academic achievement:";
            $line2 = "DEAN'S HONORS LIST ({$rank}) of scholars";
            $line3 = $gwa !== null
                ? ("who achieved a General Weighted Average of " . number_format($gwa, 4))
                : "who achieved outstanding academic performance";

            $parts = [];
            if ($semester !== '') $parts[] = $semester;
            if ($ay !== '—')       $parts[] = "AY {$ay}";
            $line4 = 'for the ' . implode(', ', $parts) . '.';

            // ---------- Draw ----------
            $this->text($img, $studentName, $X, $yName, $fsName, $fontBold ?: $fontRegular, $accentRed, 'left', 'top', $shadow);

            if ($program !== '') {
                $this->text($img, $program, $X, $yProgram, $fsProgram, $fontItalic ?: $fontRegular, $ink, 'left', 'top');
            }

            $lineH = (int) round($fsPara * 1.20);
            $this->text($img, $line1, $X, $yParaTop + $lineH * 0, $fsPara, $fontRegular, $muted, 'left', 'top');
            $this->text($img, $line2, $X, $yParaTop + $lineH * 1, $fsPara, $fontRegular, $muted, 'left', 'top');
            $this->text($img, $line3, $X, $yParaTop + $lineH * 2, $fsPara, $fontRegular, $muted, 'left', 'top');
            $this->text($img, $line4, $X, $yParaTop + $lineH * 3, $fsPara, $fontRegular, $muted, 'left', 'top');

            $this->text($img, "Date Conferred: " . $dateConfer, $X, $yDate, $fsDate, $fontRegular, $ink, 'left', 'top');

            if ($showSig) {
                $sigPath = public_path('img/cert/dean-signature.png');
                if (is_file($sigPath)) {
                    $this->placeSignature(
                        $img,
                        $sigPath,
                        $X + (int) round($W * 0.14),
                        $yDate + (int) round($H * 0.03),
                        (int) round($W * 0.10)
                    );
                }
            }

            $this->text($img, $deanName,  $X, $yDeanName, $fsDeanName, $fontBold ?: $fontRegular, $accentRed, 'left', 'top');
            $this->text($img, $deanTitle, $X, $yDeanTit,  $fsDeanTit,  $fontRegular, $ink,       'left', 'top');

            if (env('CERT_DEBUG', false)) {
                $dbg = "DBG prog='".mb_substr($program,0,40)."' sem='{$semester}' ay='{$ay}'";
                $this->text($img, $dbg, (int)round($W*0.04), (int)round($H*0.96), 16, $fontRegular, '#555', 'left', 'top');
            }

            // ---------- Save ----------
            $rel = trim(str_replace('\\', '/', $data['out_rel'] ?? ('cor/cert_' . $appId . '.png')), '/');
            if ($rel === '') $rel = 'cor/cert_' . $appId . '.png';

            $disk = Storage::disk('public');
            $abs  = $disk->path($rel);
            @mkdir(dirname($abs), 0775, true);
            if (is_file($abs)) @unlink($abs);

            $img->save($abs);

            $exists = $disk->exists($rel) || is_file($abs) || is_file(public_path('storage/'.$rel));
            if (!$exists) {
                Log::warning('Cert: saved but not visible on public disk', ['rel' => $rel, 'abs' => $abs]);
                return null;
            }

            Log::info('Cert: PNG generated', ['rel' => $rel, 'student' => $studentName, 'program' => $program, 'ay' => $ay]);
            return $rel;

        } catch (\Throwable $e) {
            Log::error('Cert: generation failed', [
                'ex' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return null;
        }
    }

    // --------- helpers ---------

    private function nameWithMiddleInitial(string $full): string
    {
        $full = trim(preg_replace('/\s+/', ' ', $full));
        if ($full === '') return '';

        $parts = preg_split('/\s+/', $full);
        if (count($parts) === 1) return $parts[0];

        $particles = ['de','del','dela','de-la','de los','de-los','de las','de-las','la','las','los','van','von','bin','binti','al'];

        $last = array_pop($parts);
        while (!empty($parts)) {
            $prev = mb_strtolower(end($parts));
            if (in_array($prev, $particles, true)) {
                $last = array_pop($parts) . ' ' . $last;
            } else {
                break;
            }
        }

        if (empty($parts)) return $last;

        $middle = array_pop($parts);
        $mi = mb_substr($middle, 0, 1);
        $mi = $mi !== '' ? mb_strtoupper($mi) . '.' : '';

        $firstNames = implode(' ', $parts);
        if ($firstNames === '') {
            $firstNames = $middle;
            $mi = '';
        }

        return trim($firstNames . ' ' . ($mi ? $mi . ' ' : '') . $last);
    }

    private function firstFont(array $candidates): ?string
    {
        foreach ($candidates as $p) {
            if ($p && is_file($p)) return $p;
        }
        return null;
    }

    private function text($img, string $text, int $x, int $y, int $size, ?string $font, string $color,
                          string $align='left', string $valign='top', ?string $shadow=null): void
    {
        if ($shadow) {
            $img->text($text, $x+2, $y+2, function (FontFactory $f) use ($font,$size,$align,$valign,$shadow) {
                if ($font && is_file($font)) $f->filename($font);
                $f->size($size); $f->color($shadow); $f->align($align); $f->valign($valign);
            });
        }
        $img->text($text, $x, $y, function (FontFactory $f) use ($font,$size,$align,$valign,$color) {
            if ($font && is_file($font)) $f->filename($font);
            $f->size($size); $f->color($color); $f->align($align); $f->valign($valign);
        });
    }

    private function placeSignature($img, string $sigAbs, int $x, int $baselineY, int $targetW): void
    {
        try {
            $sig = $this->im->read($sigAbs);
            $sw = $sig->width(); $sh = $sig->height();
            if ($sw <= 0 || $sh <= 0) return;
            $scale = $targetW / $sw;
            $dw = (int) round($sw * $scale);
            $dh = (int) round($sh * $scale);
            $sig->resize($dw, $dh);
            $img->place($sig, 'top-left', $x, $baselineY - (int) round($dh * 0.70));
        } catch (\Throwable $e) {
            Log::warning('Cert: signature place failed', ['err' => $e->getMessage()]);
        }
    }

    private function honorsFromGwa(?float $gwa): string
    {
        if ($gwa === null) return '';
        if ($gwa <= 1.25) return 'First Honors';
        if ($gwa <= 1.50) return 'Second Honors';
        if ($gwa <= 1.75) return 'Third Honors';
        return '';
    }

    private function normalizeAy(string $ay): string
    {
        $ay = trim($ay);
        if ($ay === '') return '—';
        $ay = preg_replace('/\s*[-–—]\s*/u', ' – ', $ay);
        $ay = preg_replace('/\s+/', ' ', $ay);
        return $ay;
    }
}
