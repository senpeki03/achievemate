<?php

namespace App\Http\Controllers\Dean;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

use App\Models\Post;
use App\Models\Rank;
use App\Models\Application;
use App\Models\StudentManage;
use App\Models\StudentNotification;
use App\Models\Approved;
use App\Models\UserDesignation;
use App\Models\StudentCourse; // 👈 added

use App\Mail\DeansListApprovedMail;
use App\Services\AwardService; // optional – we won't rely on it but keep DI compatible

class DeanApplicationController extends Controller
{
    public function __construct(private ?AwardService $awardService = null) {}

    /* =======================================================================
     |  Single status update (For Approval | Approved)
     * =======================================================================*/
    public function updateStatus(Request $request)
    {
        $data = $request->validate([
            'id'     => ['required','integer'],
            'status' => ['required','string','in:Approved,For Approval'],
        ]);

        $app = Application::findOrFail((int)$data['id']);
        $app->Status = (string)$data['status'];
        $app->save();

        if ($app->Status === 'Approved') {
            // 1) Save / update row in APPROVED table
            $this->storeApprovedRow($app);

            // 2) Cert + notification + email
            $this->handleApproved($app);
        }

        return response()->json(['ok' => true]);
    }

    /* =======================================================================
     |  Bulk approve
     * =======================================================================*/
    public function bulkApprove(Request $request)
    {
        $data = $request->validate([
            'application_ids'   => ['required','array','min:1'],
            'application_ids.*' => ['integer'],
        ]);

        $apps = Application::whereIn('Application_id', $data['application_ids'])->get();
        if ($apps->isEmpty()) {
            return response()->json(['message' => 'No matching applications.'], 422);
        }

        foreach ($apps as $app) {
            $app->Status = 'Approved';
            $app->save();

            // 1) Save / update row in APPROVED table
            $this->storeApprovedRow($app);

            // 2) Cert + notification + email
            $this->handleApproved($app);
        }

        return response()->json(['message' => 'Selected applications approved.']);
    }

    /* =======================================================================
     |  Internals
     * =======================================================================*/

    /**
     * Save / update record sa `approved` table.
     * - 1 row per Student_id
     * - Stores Approved Date + Academic Year + Dean's User_id (from user_designation)
     */
    private function storeApprovedRow(Application $app): Approved
    {
        $login  = auth()->user();
        $userId = null;

        if ($login && isset($login->Login_id)) {
            $designation = UserDesignation::where('Login_id', $login->Login_id)->first();

            if ($designation) {
                $userId = $designation->User_id; // FK to user_manage.User_id
            }
        }

        // Academic year: from Application if may field, else default AY
        $ay = $app->ay ?? $this->currentAcademicYear();

        $values = [
            'Date'          => now()->toDateString(), // Approved Date
            'Academic_year' => $ay,
        ];

        if (!is_null($userId)) {
            $values['User_id'] = $userId;
        }

        $approved = Approved::updateOrCreate(
            ['Student_id' => $app->Student_id],
            $values
        );

        Log::info('Approved row stored', [
            'student_id' => $app->Student_id,
            'user_id'    => $userId,
            'ay'         => $ay,
            'date'       => $approved->Date,
        ]);

        return $approved;
    }

    /**
     * Helper to compute current Academic Year string, e.g. "2025-2026".
     */
    private function currentAcademicYear(): string
    {
        $year = (int) now()->year;
        $next = $year + 1;
        return "{$year}-{$next}";
    }

    /**
     * Everything that must happen once a Dean approves an application.
     * (certificate + award notification + email)
     */
    private function handleApproved(Application $app): void
    {
        try {
            // 1) Generate personalized certificate (PNG on public disk)
            $relCert = $this->generateCertificate($app);

            // 2) Create/Update the single award notification for this student+app
            $notif = $this->upsertAwardNotification($app, $relCert);

            // 3) Email the student with the claim link
            $this->sendApprovedEmail($app, $notif);

        } catch (\Throwable $e) {
            Log::error('Dean handleApproved failed', [
                'err'    => $e->getMessage(),
                'app_id' => $app->Application_id ?? $app->id,
            ]);
        }
    }

    /**
     * Generate certificate PNG (Semester & AY are resolved by DeanCertDataBuilder/Post).
     */
    private function generateCertificate(Application $app): string
    {
        try {
            // ========= Rank / honor tier =========
            $honorTier = null;

            if (!empty($app->Rank)) {
                $honorTier = $app->Rank;
            } elseif (is_numeric($app->GWA)) {
                try {
                    $rule = Rank::ruleForGwa((float) $app->GWA);
                    if ($rule && $rule->Rank) {
                        $honorTier = $rule->Rank;
                    }
                } catch (\Throwable $e) {
                    Log::warning('Dean cert: failed to resolve Rank from rules table', [
                        'err' => $e->getMessage(),
                        'gwa' => $app->GWA,
                    ]);
                }
            }

            // ========= Build data via DeanCertDataBuilder =========
            $data = app(\App\Services\DeanCertDataBuilder::class)->buildFromStudent(
                (int) $app->Student_id,
                [
                    'template_png' => public_path('img/cert/dean-template.png'),
                    'out_rel'      => "certificates/deans_lister_app_{$app->Application_id}.png",
                    'app_id'       => (int) ($app->Application_id ?? $app->id),

                    // only pass honor_tier; Semester & AY will come from Post/StudentCourse inside builder
                    'honor_tier'   => $honorTier,
                ]
            );

            // ========= Generate PNG =========
            $rel = app(\App\Services\CertificateImageService::class)->makeDeansCertPng($data);

            if ($rel) {
                return $rel;
            }

            // Fallback: placeholder PNG
            $relFallback = 'certificates/deans_lister_placeholder.png';
            if (!Storage::disk('public')->exists($relFallback)) {
                Storage::disk('public')->put(
                    $relFallback,
                    file_get_contents(public_path('img/cert/dean-template.png'))
                );
            }
            return $relFallback;

        } catch (\Throwable $e) {
            Log::warning('Cert generation fallback', ['err' => $e->getMessage()]);
            $relFallback = 'certificates/deans_lister_placeholder.png';
            if (!Storage::disk('public')->exists($relFallback)) {
                Storage::disk('public')->put(
                    $relFallback,
                    file_get_contents(public_path('img/cert/dean-template.png'))
                );
            }
            return $relFallback;
        }
    }

    /**
     * Ensure there is only ONE award notification per (student, application).
     * If it exists, update paths but keep the same claim_token.
     */
    private function upsertAwardNotification(Application $app, string $relCert)
    {
        $appId = (int)($app->Application_id ?? $app->id);

        $existing = StudentNotification::query()
            ->where('Student_id', $app->Student_id)
            ->where('type', 'deans_lister_award')
            ->where('data->application_id', $appId)
            ->latest('StudentNotification_id')
            ->first();

        $payloadData = [
            'application_id'   => $appId,
            'certificate_path' => $relCert,
            'badge_path'       => 'assets/badges/deans_lister.png',
        ];

        if ($existing) {
            $existing->title     = "Dean's Lister — Certificate & Badge";
            $existing->message   = "Your Dean's Lister certificate is ready. Tap to claim.";
            $existing->data      = $payloadData;
            $existing->claimable = true;
            $existing->save();

            return $existing;
        }

        // Create a fresh one
        return StudentNotification::create([
            'Student_id'  => $app->Student_id,
            'type'        => 'deans_lister_award',
            'title'       => "Dean's Lister — Certificate & Badge",
            'message'     => "Your Dean's Lister certificate is ready. Tap to claim.",
            'data'        => $payloadData,
            'claim_token' => Str::uuid()->toString(),
            'claimable'   => true,
            'is_read'     => false,
        ]);
    }

    /**
     * Compose and send the email (claim link if we have a token, else notifications page).
     * Program/College from student_course; Semester/AY from Post when available.
     */
    private function sendApprovedEmail(Application $app, ?StudentNotification $notif = null): void
    {
        try {
            // Student + login
            $student = StudentManage::with(['login'])
                ->where('Student_id', $app->Student_id)
                ->first();

            if (!$student) {
                Log::warning('Dean approval: student not found', [
                    'student_id' => $app->Student_id,
                    'app_id'     => $app->Application_id ?? $app->id,
                ]);
                return;
            }

            $email = $student?->login?->username
                  ?? $student?->Email
                  ?? null;

            if (!$email) {
                Log::warning('Dean approval: no email/username found', [
                    'student_id' => $app->Student_id,
                    'app_id'     => $app->Application_id ?? $app->id,
                ]);
                return;
            }

            // Claim URL preferred
            $downloadLink = ($notif && $notif->claim_token)
                ? route('student.award.claim', ['token' => $notif->claim_token])
                : (Route::has('student.notifications')
                    ? route('student.notifications')
                    : url('/student/notifications'));

            $studentName = trim(implode(' ', array_filter([
                $student?->First_name, $student?->Middle_name, $student?->Last_name,
            ]))) ?: 'Student';

            $studentNo = $student?->SRCODE ?? (string)($student?->Student_id ?? $app->Student_id);

            // ================= Program / College via student_course =================
            $programName = '—';
            $collegeName = '—';

            try {
                $sc = StudentCourse::with(['program.college', 'college'])
                    ->where('Student_id', $app->Student_id)
                    ->latest('StudentCourse_id')
                    ->first();

                if ($sc) {
                    if ($sc->program) {
                        $programName = $sc->program->Program_name ?? $programName;

                        // college either from direct relation or from program->college
                        if ($sc->program->college) {
                            $collegeName = $sc->program->college->College_name ?? $collegeName;
                        }
                    }

                    if ($collegeName === '—' && $sc->college) {
                        $collegeName = $sc->college->College_name ?? $collegeName;
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('Dean email: failed to resolve program/college from student_course', [
                    'err' => $e->getMessage(),
                ]);
            }

            // If still blank, fallback to raw fields on StudentManage
            if ($programName === '—') {
                $programName = $student->Program_name ?? $student->Program ?? '—';
            }
            if ($collegeName === '—') {
                $collegeName = $student->College_name ?? $student->College ?? '—';
            }

            // ================= Year level, GWA, Rank =================
            $yearLevel   = (string)($app->YearLevel ?? $app->year_level ?? $student?->Year ?? '—');
            $gwaVal      = $app->GWA ?? null;
            $gwaTxt      = is_numeric($gwaVal) ? number_format((float)$gwaVal, 2) : '—';
            $rankTxt     = $app->rank ?? $app->distinction ?? "Dean's Lister";

            // ================= Semester / AY via Post (if possible) =================
            $termTxt = $app->term ?? null;
            $ayTxt   = $app->ay   ?? null;

            try {
                $login = auth()->user();
                if ($login && isset($login->Login_id)) {
                    $designation = UserDesignation::where('Login_id', $login->Login_id)->first();

                    if ($designation) {
                        $now = now()->toDateString();

                        $postQuery = Post::query()
                            ->where('UserDesignation_id', $designation->UserDesignation_id)
                            ->whereDate('Start_date', '<=', $now)
                            ->whereDate('End_date', '>=', $now);

                        // Optional: filter by student's program if we have one
                        if (isset($sc) && $sc && $sc->Program_id) {
                            $postQuery->whereHas('userDesignation', function ($q) use ($sc) {
                                $q->where('Program_id', $sc->Program_id);
                            });
                        }

                        $post = $postQuery->latest('Post_id')->first();

                        if ($post) {
                            if (!$termTxt && $post->Semester) {
                                $termTxt = $post->Semester; // usually already "First Semester" / "Second Semester"
                            }
                            if (!$ayTxt && $post->Academic_year) {
                                $ayTxt = $post->Academic_year;
                            }
                        }
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('Dean email: failed to resolve term/AY from Post', [
                    'err' => $e->getMessage(),
                ]);
            }

            // final fallbacks
            if (!$termTxt) {
                $termTxt = now()->month <= 5 ? '2nd Semester' : '1st Semester';
            }
            if (!$ayTxt) {
                $ayTxt = $this->currentAcademicYear();
            }

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

            Mail::to($email)->send(new DeansListApprovedMail($payload));

            Log::info('DeansList mail sent', [
                'to'             => $email,
                'application_id' => $app->Application_id ?? $app->id,
            ]);
        } catch (\Throwable $e) {
            Log::error('Email send failed', [
                'app_id' => $app->Application_id ?? $app->id,
                'err'    => $e->getMessage(),
            ]);
        }
    }
}
