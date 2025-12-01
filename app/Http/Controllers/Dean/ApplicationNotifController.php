<?php

namespace App\Http\Controllers\Dean;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

use App\Models\Application;
use App\Models\StudentManage;
use App\Models\StudentNotification;
use App\Models\StudentCourse;

use App\Services\AwardService;
use App\Mail\DeansListApprovedMail;

class ApplicationNotifController extends Controller
{
    public function __construct(private AwardService $awardService) {}

    // Single update (Approve/Verified/etc.)
    public function updateStatus(Request $request)
    {
        $data = $request->validate([
            'id'     => ['required','integer'],
            'status' => ['required', Rule::in(['For Approval','Approved'])],
        ]);

        $app = Application::findOrFail($data['id']);
        $app->Status = $data['status'];
        $app->save();

        if ($app->Status === 'Approved') {
            // 1) optional hook (generates file + base notif if you kept it there)
            try { 
                $this->awardService->onApproved($app); 
            } catch (\Throwable $e) { 
                Log::warning('AwardService onApproved failed', ['e'=>$e->getMessage(), 'app'=>$app->Application_id]); 
            }

            // 2) email the student
            $this->sendApprovedEmail($app);

            // 3) ensure a StudentNotification row exists (cert + badge + claim_token)
            $this->createAwardNotification($app);
        }

        return response()->json(['ok' => true]);
    }

    // Bulk approve selected application IDs
    public function bulkApprove(Request $request)
    {
        $data = $request->validate([
            'application_ids'   => ['required','array','min:1'],
            'application_ids.*' => ['integer'],
        ]);

        DB::beginTransaction();
        try {
            $apps = Application::query()
                ->whereIn('Application_id', $data['application_ids'])
                ->lockForUpdate()
                ->get();

            if ($apps->isEmpty()) {
                return response()->json(['message' => 'No matching applications.'], 422);
            }

            foreach ($apps as $app) {
                $app->Status = 'Approved';
                $app->save();

                try { 
                    $this->awardService->onApproved($app); 
                } catch (\Throwable $e) { 
                    Log::warning('AwardService onApproved failed', ['e'=>$e->getMessage(), 'app'=>$app->Application_id]); 
                }

                $this->sendApprovedEmail($app);
                $this->createAwardNotification($app);
            }

            DB::commit();
            return response()->json(['message' => 'Selected students approved.']);
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);
            return response()->json(['message' => 'Bulk approve failed: '.$e->getMessage()], 500);
        }
    }

    /* ------------------------ internals ------------------------ */

    private function sendApprovedEmail(Application $app, ?StudentNotification $notif = null): void
    {
        try {
            Log::info('DeanEmail DEBUG: start sendApprovedEmail', [
                'app_id'     => $app->Application_id ?? $app->id,
                'student_id' => $app->Student_id,
            ]);

            // ===== Student + login =====
            $student = StudentManage::with('login')
                ->where('Student_id', $app->Student_id)
                ->first();

            if (!$student) {
                Log::warning('DeanEmail DEBUG: student not found', [
                    'student_id' => $app->Student_id,
                ]);
                return;
            }

            Log::info('DeanEmail DEBUG: student basic', [
                'Student_id'    => $student->Student_id,
                'SRCODE'        => $student->SRCODE ?? null,
                'College_name'  => $student->College_name ?? null,
                'College_raw'   => $student->College ?? null,
                'Program_name'  => $student->Program_name ?? null,
            ]);

            $email = $student?->login?->username
                ?? $student?->Email
                ?? null;

            if (!$email) {
                Log::warning('DeanEmail DEBUG: no email/username found', [
                    'student_id' => $student->Student_id,
                ]);
                return;
            }

            // ===== Claim URL =====
            $downloadLink = ($notif && $notif->claim_token)
                ? route('student.award.claim', ['token' => $notif->claim_token])
                : (Route::has('student.notifications')
                    ? route('student.notifications')
                    : url('/student/notifications'));

            // ===== Basic student info =====
            $studentName = trim(implode(' ', array_filter([
                $student?->First_name,
                $student?->Middle_name,
                $student?->Last_name,
            ]))) ?: 'Student';

            $studentNo = $student?->SRCODE ?? (string)($student?->Student_id ?? $app->Student_id);

            // ============================================================
            //  PROGRAM & COLLEGE  (from StudentCourse)
            // ============================================================
            $programName = '—';
            $collegeName = '—';
            $sc = null;

            try {
                $sc = StudentCourse::with(['program.college', 'college'])
                    ->where('Student_id', $app->Student_id)
                    ->latest('StudentCourse_id')
                    ->first();

                Log::info('DeanEmail DEBUG: StudentCourse raw', [
                    'has_sc' => (bool) $sc,
                    'sc_row' => $sc ? $sc->toArray() : null,
                ]);

                if ($sc) {
                    // Program from StudentCourse->program
                    if ($sc->program) {
                        $programName = $sc->program->Program_name ?? $programName;
                    }

                    // College via StudentCourse->college relation
                    if ($sc->college) {
                        $collegeName = $sc->college->College_name ?? $collegeName;
                    }

                    // Kung di pa rin, try Program->college
                    if ($collegeName === '—' && $sc->program && $sc->program->college) {
                        $collegeName = $sc->program->college->College_name ?? $collegeName;
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('DeanEmail DEBUG: StudentCourse lookup failed', [
                    'err' => $e->getMessage(),
                ]);
            }

            Log::info('DeanEmail DEBUG: after StudentCourse', [
                'program_from_sc' => $programName,
                'college_from_sc' => $collegeName,
            ]);

            // FINAL fallback: StudentManage.College_name LANG, hindi yung JSON sa College
            if ($collegeName === '—' && !empty($student->College_name)) {
                $collegeName = $student->College_name;
                Log::info('DeanEmail DEBUG: college fallback to StudentManage.College_name', [
                    'collegeName' => $collegeName,
                ]);
            }

            // Optional fallback for program kung meron sa student_manage
            if ($programName === '—' && !empty($student->Program_name)) {
                $programName = $student->Program_name;
                Log::info('DeanEmail DEBUG: program fallback to StudentManage.Program_name', [
                    'programName' => $programName,
                ]);
            }

            // ============================================================
            //  YEAR LEVEL / GWA (4 decimals) / RANK
            // ============================================================
            $yearLevel = (string)($app->YearLevel ?? $app->year_level ?? $student?->Year ?? '—');

            $gwaVal = $app->GWA ?? null;
            $gwaTxt = is_numeric($gwaVal)
                ? number_format((float)$gwaVal, 4)
                : '—';

            Log::info('DeanEmail DEBUG: GWA values', [
                'gwa_raw'   => $gwaVal,
                'gwa_final' => $gwaTxt,
            ]);

            $rankTxt = $app->rank ?? $app->distinction ?? "Dean's Lister";

            // ============================================================
            //  TERM / A.Y.
            // ============================================================
            $termTxt = $app->term ?? null;
            $ayTxt   = $app->ay   ?? null;

            if (!$termTxt) {
                $termTxt = now()->month <= 5 ? '2nd Semester' : '1st Semester';
            }
            if (!$ayTxt) {
                $year = (int) now()->year;
                $ayTxt = $year . '-' . ($year + 1);
            }

            // ============================================================
            //  PAYLOAD TO MAILABLE
            // ============================================================
            $payload = [
                'studentName'        => $studentName,
                'studentId'          => $studentNo,
                'program'            => $programName,
                'yearLevel'          => $yearLevel,
                'college'            => $collegeName,
                'gwa'                => $gwaTxt,
                'rankOrDistinction'  => $rankTxt,
                'term'               => $termTxt,
                'ay'                 => $ayTxt,
                'downloadLink'       => $downloadLink,
                'universityName'     => config('app.university_name', 'Your University'),
                'deanName'           => auth()->user()->name ?? 'Dean',
                'deanTitle'          => 'Dean',
                'systemName'         => config('app.name'),
                'supportEmail'       => config('mail.from.address'),
            ];

            Log::info('DeanEmail DEBUG: final payload for mail', $payload);

            Mail::to($email)->send(new DeansListApprovedMail($payload));

            Log::info('DeanEmail DEBUG: mail sent OK', [
                'to'       => $email,
                'student'  => $studentNo,
            ]);
        } catch (\Throwable $e) {
            Log::error('DeanEmail DEBUG: sendApprovedEmail failed', [
                'err'    => $e->getMessage(),
                'app_id' => $app->Application_id ?? $app->id,
            ]);
        }
    }

    private function createAwardNotification(Application $app): void
    {
        try {
            // ---------- Determine badge by tier ----------
            $tier = $this->determineHonorTier($app); // First Honors / Second Honors / null
            $badgeAbs = $this->badgeForTier($tier);  // absolute filesystem path or null

            // ---------- Build student basics (name/program) for the cert ----------
            $student = StudentManage::with(['studentCourse.program'])
                ->where('Student_id', $app->Student_id)
                ->first();

            if (!$student) {
                Log::error('Student not found for award notification', ['student_id' => $app->Student_id]);
                return;
            }

            $studentName = trim(implode(' ', array_filter([
                $student?->First_name,
                $student?->Middle_name,
                $student?->Last_name,
            ]))) ?: 'STUDENT NAME';

            // Get program from student course
            $studentCourse = $student->studentCourse->first();
            $programName = $studentCourse?->program?->Program_name ?: '—';

            // ---------- Template + output ----------
            $templateAbs = public_path('img/cert/dean-template.png');  // your base PNG template
            $outRel = 'certificates/deans_lister_' . $app->Application_id . '.png';

            $gwaVal = $app->GWA ?? null;
            $gwaTxt = is_numeric($gwaVal) ? (string)$gwaVal : null;

            // Prefer explicit term / AY if you keep them in $app
            $semester  = $app->term ?? (now()->month <= 5 ? 'Second Semester' : 'First Semester');
            $ay        = $app->ay   ?? sprintf('%d-%d', now()->year, now()->addYear()->year);

            $badgePublicRel = null;
            if ($badgeAbs) {
                // compute public-relative (for front-end) if under public/
                $badgePublicRel = 'img/cert/' . basename($badgeAbs); // matches your provided location
            }

            // ---------- Generate the certificate (with badge if available) ----------
            $generatedRel = null;
            if (is_file($templateAbs)) {
                /** @var \App\Services\CertificateImageService $imgSvc */
                $imgSvc = app(\App\Services\CertificateImageService::class);

                $generatedRel = $imgSvc->makeDeansCertPng([
                    'template_png' => $templateAbs,
                    'out_rel'      => $outRel,
                    'app_id'       => $app->Application_id,
                    'student_name' => $studentName,
                    'program'      => $programName,
                    'gwa'          => $gwaTxt,
                    'semester'     => $semester,
                    'school_year'  => $ay,
                    'honor_tier'   => $tier,
                    'badge_abs'    => $badgeAbs,  // <-- this triggers the overlay
                    // you may also pass dean_name/dean_title/show_signature here if needed
                ]);
            } else {
                Log::warning('Certificate template not found', ['template_path' => $templateAbs]);
            }

            // ---------- Fallback if generation fails ----------
            $relCert = $generatedRel ?: 'certificates/deans_lister_placeholder.png';
            if (!Storage::disk('public')->exists($relCert)) {
                // ensure we still have *something* to display
                Storage::disk('public')->put($relCert, @file_get_contents(public_path('img/cert/dean-template.png')) ?: '');
            }

            // ---------- Upsert notification ----------
            $dataPayload = [
                'application_id'   => $app->Application_id,
                'certificate_path' => $relCert,          // on public disk
                'badge_path'       => $badgePublicRel,   // public/ relative for UI use
                'honor_tier'       => $tier,             // for front-end labels
            ];

            $existing = StudentNotification::where('Student_id', $app->Student_id)
                ->where('type', 'deans_lister_award')
                ->whereNull('claimed_at')
                ->first();

            if ($existing) {
                $existing->update([
                    'title'     => "Dean's Lister — Certificate & Badge",
                    'message'   => "Your Dean's Lister certificate is ready. Tap to claim.",
                    'data'      => $dataPayload,
                    'claimable' => true,
                    'is_read'   => false,
                ]);
                Log::info('Updated existing award notification', ['student_id' => $app->Student_id, 'notification_id' => $existing->StudentNotification_id]);
            } else {
                $newNotification = StudentNotification::create([
                    'Student_id'  => $app->Student_id,
                    'type'        => 'deans_lister_award',
                    'title'       => "Dean's Lister — Certificate & Badge",
                    'message'     => "Your Dean's Lister certificate is ready. Tap to claim.",
                    'data'        => $dataPayload,
                    'claim_token' => Str::uuid()->toString(),
                    'claimable'   => true,
                    'is_read'     => false,
                ]);
                Log::info('Created new award notification', ['student_id' => $app->Student_id, 'notification_id' => $newNotification->StudentNotification_id]);
            }

        } catch (\Throwable $e) {
            Log::error('createAwardNotification failed', ['err' => $e->getMessage(), 'student_id' => $app->Student_id]);
        }
    }

    private function determineHonorTier(Application $app): ?string
    {
        // 1) Prefer explicit rank/distinction if present
        $rank = trim((string)($app->rank ?? $app->distinction ?? ''));
        $rankLower = mb_strtolower($rank);

        if ($rankLower !== '') {
            if (str_contains($rankLower, 'first'))  return 'First Honors';
            if (str_contains($rankLower, '1st'))    return 'First Honors';
            if (str_contains($rankLower, 'second')) return 'Second Honors';
            if (str_contains($rankLower, '2nd'))    return 'Second Honors';
        }

        // 2) Fall back to GWA thresholds (same rule as your service)
        $gwa = null;
        if (isset($app->GWA) && is_numeric($app->GWA)) {
            $gwa = (float) $app->GWA;
        }
        if ($gwa === null) return null;

        if ($gwa <= 1.25) return 'First Honors';
        if ($gwa <= 1.50) return 'Second Honors';
        return null;
    }

    private function badgeForTier(?string $tier): ?string
    {
        if (!$tier) return null;

        // use public_path to map your given file paths
        // Gold for First Honors, Silver for Second Honors
        if ($tier === 'First Honors') {
            $abs = public_path('img/cert/badge_gold.png');
            return is_file($abs) ? $abs : null;
        }
        if ($tier === 'Second Honors') {
            $abs = public_path('img/cert/badge_silver.png');
            return is_file($abs) ? $abs : null;
        }
        return null;
    }
}