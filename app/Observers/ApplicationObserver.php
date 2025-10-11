<?php

namespace App\Observers;

use App\Models\Application;
use App\Models\Portfolio;                 // ⬅️ NEW
use App\Models\StudentNotification;
use App\Mail\DeansListApprovedMail;
use App\Services\CertificateImageService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ApplicationObserver
{
    public function updated(Application $app): void
    {
        // Only when Status actually becomes “Approved”
        if (!$app->wasChanged('Status')) return;
        if (strcasecmp((string) $app->Status, 'Approved') !== 0) return;

        /* ---------------- 1) Student + Program (defensive) ---------------- */
        $student = DB::table('student_manage')->where('Student_id', $app->Student_id)->first();

        $studentName = 'Student';
        $programLine = '';
        if ($student) {
            $studentName = trim(
                ($student->First_name ?? '') . ' ' .
                ($student->Middle_name ?? '') . ' ' .
                ($student->Last_name ?? '')
            ) ?: 'Student';

            $programLine = $student->Program_name ?? $student->Course ?? '';
        }

        /* ---------------- 2) AY / Semester (defensive) -------------------- */
        $semester = '';
        $schoolYear = '';
        try {
            $schema = DB::getSchemaBuilder();
            $semCol = $schema->hasColumn('curriculum_ay', 'Semester')    ? 'Semester'
                    : ($schema->hasColumn('curriculum_ay', 'semester')    ? 'semester' : null);
            $syCol  = $schema->hasColumn('curriculum_ay', 'School_year') ? 'School_year'
                    : ($schema->hasColumn('curriculum_ay', 'school_year') ? 'school_year' : null);

            if ($semCol || $syCol) {
                $ay = DB::table('student_manage as sm')
                    ->leftJoin('curriculum as c', 'c.Curriculum_id', '=', 'sm.Curriculum_id')
                    ->leftJoin('curriculum_ay as cay', 'cay.CurriculumAy_id', '=', 'c.CurriculumAy_id')
                    ->where('sm.Student_id', $app->Student_id)
                    ->selectRaw(
                        ($semCol ? "COALESCE(cay.$semCol,'')" : "''") . " as sem, " .
                        ($syCol  ? "COALESCE(cay.$syCol,'')"  : "''") . " as sy"
                    )
                    ->first();

                $semester   = $ay->sem ?? '';
                $schoolYear = $ay->sy  ?? '';
            }
        } catch (\Throwable $e) {
            Log::warning('AY lookup skipped', ['app_id' => $app->Application_id, 'err' => $e->getMessage()]);
        }

        /* ---------------- 3) Generate PNG certificate --------------------- */
        $pngAbs = null;                                     // absolute path returned by the service
        $outRel = 'cor/cert_' . (int) $app->Application_id . '.png'; // relative path on the "public" disk
        $certUrl = null;                                    // public URL for UI/email

        try {
            if (!extension_loaded('gd')) {
                Log::warning('Skipping PNG generation (GD extension missing)', [
                    'app_id' => $app->Application_id,
                ]);
            } else {
                /** @var CertificateImageService $cert */
                $cert = app(CertificateImageService::class);

                // Prefer Poppler-produced page if available; fallback to static template
                $templateAbs = $this->resolveTemplateAbs($app);

                if (!$templateAbs || !is_file($templateAbs)) {
                    Log::warning('Template PNG missing', ['template' => $templateAbs]);
                } else {
                        $pngAbs = $cert->makeDeansCertPng([
                            'app_id'        => (int) $app->Application_id,
                            'student_name'  => $studentName,
                            'program'       => $programLine,
                            'gwa'           => $app->GWA ?? $app->gwa ?? '',
                            'semester'      => $semester,
                            'school_year'   => $schoolYear,
                            'date_conferred'=> now()->format('F d, Y'),
                            'template_png'  => $templateAbs,
                            'out_rel'       => $outRel,
                        ]);

                    if (!Storage::disk('public')->exists($outRel)) {
                        Log::warning('PNG not created on public disk', [
                            'app_id' => $app->Application_id,
                            'rel'    => $outRel,
                            'abs'    => $pngAbs,
                        ]);
                        $pngAbs = null;
                        $outRel = null;
                    } else {
                        $certUrl = Storage::disk('public')->url($outRel);
                        Log::info('Cert: PNG generated', [
                            'app_id' => $app->Application_id,
                            'rel'    => $outRel,
                            'abs'    => $pngAbs,
                            'url'    => $certUrl,
                        ]);
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::error('PNG certificate generation failed', [
                'app_id' => $app->Application_id,
                'err'    => $e->getMessage(),
            ]);
            $pngAbs = null;
            $outRel = null;
        }

        /* ---------------- 3.5) UPSERT to portfolios ----------------------- */
        // UI reads from portfolios and/or notifications. Ensure portfolios has the relative path.
        try {
            if ($outRel) {
                Portfolio::updateOrCreate(
                    [
                        'Student_id' => (int) $app->Student_id,
                        'type'       => 'DeanLister',
                        'title'      => 'Dean’s Lister',
                    ],
                    [
                        'description'      => 'Auto-added on approval',
                        'certificate_path' => $outRel, // RELATIVE (e.g., cor/cert_44.png)
                        'badge_path'       => null,
                    ]
                );
                Log::info('Portfolio upserted with cert path', [
                    'student_id' => (int) $app->Student_id,
                    'app_id'     => (int) $app->Application_id,
                    'rel'        => $outRel,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('Portfolio upsert failed', [
                'app_id' => $app->Application_id,
                'err'    => $e->getMessage(),
            ]);
        }

        /* ---------------- 4) In-app notification -------------------------- */
        try {
            StudentNotification::create([
                'Student_id'  => (int) $app->Student_id,
                'type'        => 'deans_lister_award',
                'title'       => "Dean’s Lister Award",
                'message'     => "You qualified for Dean’s Lister. Tap to claim your badge and certificate.",
                'data'        => [
                    'application_id'   => (int) $app->Application_id,
                    'certificate_path' => $outRel,  // ⬅️ RELATIVE for controller check
                    'certificate_url'  => $certUrl, // optional convenience for links
                    'badge_path'       => 'assets/badges/deans_lister.png',
                    'semester'         => $semester,
                    'school_year'      => $schoolYear,
                    'program'          => $programLine,
                ],
                'claim_token' => Str::random(40),
                'claimable'   => true,
                'is_read'     => false,
            ]);
        } catch (\Throwable $e) {
            Log::error('Creating StudentNotification failed', [
                'app_id' => $app->Application_id,
                'err'    => $e->getMessage(),
            ]);
        }

        /* ---------------- 5) Email (attach if present) -------------------- */
        try {
            $to = DB::table('login as l')
                ->join('student_manage as sm', 'sm.Login_id', '=', 'l.Login_id')
                ->where('sm.Student_id', $app->Student_id)
                ->value('l.username');

            if (!$to || !str_contains($to, '@')) {
                Log::warning('No valid login.username to email', [
                    'student_id' => $app->Student_id,
                    'resolved'   => $to,
                    'app_id'     => $app->Application_id,
                ]);
                return;
            }

            $mailable = new DeansListApprovedMail($studentName, $certUrl);

            if ($outRel && Storage::disk('public')->exists($outRel)) {
                $mailable->attachFromStorageDisk(
                    'public',
                    $outRel,
                    'deans_certificate.png',
                    ['mime' => 'image/png']
                );
            }

            Mail::to($to)->send($mailable);
            Log::info('DeansListApprovedMail sent', ['to' => $to, 'app_id' => $app->Application_id]);
        } catch (\Throwable $e) {
            Log::error('Email send failed', ['app_id' => $app->Application_id, 'err' => $e->getMessage()]);
        }
    }

    /**
     * Resolve the ABSOLUTE path to the template PNG to render on:
     * - Prefer the Poppler-generated page saved as Application->cor_png_basename (REL path under storage/app).
     * - Handle odd suffix variants (.png, .page1.png, .png.page1.png).
     * - Fallback to public/img/cert/dean-template.png if none found.
     */
    protected function resolveTemplateAbs(Application $app): ?string
    {
        // If you saved the Poppler output basename (recommended)
        $rel = (string) ($app->cor_png_basename ?? '');
        if ($rel !== '') {
            $rel = ltrim($rel, '/');
            if (!str_starts_with($rel, 'cor/')) {
                $rel = 'cor/' . $rel;
            }

            // try exact first
            $cand = Storage::path($rel);
            if (is_file($cand)) return $cand;

            // try common variants when basename was stored without final suffixes
            $base = preg_replace('/(\.png)?(\.page\d+)?(\.png)?$/i', '', $rel);
            $variants = [
                $base . '.png.page1.png',
                $base . '.png.page1',
                $base . '.page1.png',
                $base . '.png',
            ];
            foreach ($variants as $v) {
                $p = Storage::path($v);
                if (is_file($p)) return $p;
            }
        }

        // Fallback: static template in public
        $fallback = public_path('img/cert/dean-template.png');
        return is_file($fallback) ? $fallback : null;
    }
}
