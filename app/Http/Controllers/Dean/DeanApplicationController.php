<?php

namespace App\Http\Controllers\Dean;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

use App\Models\Application;
use App\Models\StudentManage;
use App\Models\StudentNotification;

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
        $request->validate([
            'id'     => ['required','integer'],
            'status' => ['required','string','in:Approved,For Approval'],
        ]);

        $app = Application::findOrFail((int)$request->input('id'));
        $app->Status = (string)$request->input('status');
        $app->save();

        if ($app->Status === 'Approved') {
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
            $this->handleApproved($app);
        }

        return response()->json(['message' => 'Selected applications approved.']);
    }

    /* =======================================================================
     |  Internals
     * =======================================================================*/

    /**
     * Everything that must happen once a Dean approves an application.
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
                'err' => $e->getMessage(),
                'app_id' => $app->Application_id ?? $app->id,
            ]);
        }
    }

    /**
     * Build data via DeanCertDataBuilder and render certificate PNG.
     * Returns the relative public-disk path (e.g. certificates/deans_lister_app_110.png).
     */
    private function generateCertificate(Application $app): string
    {
        try {
            $data = app(\App\Services\DeanCertDataBuilder::class)->buildFromStudent(
                (int)$app->Student_id,
                [
                    'template_png' => public_path('img/cert/dean-template.png'),
                    'out_rel'      => "certificates/deans_lister_app_{$app->Application_id}.png",
                    'app_id'       => (int)($app->Application_id ?? $app->id),
                ]
            );

            $rel = app(\App\Services\CertificateImageService::class)->makeDeansCertPng($data);

            if ($rel) {
                return $rel;
            }

            // Fallback: placeholder derived from template (still a PNG)
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
            $existing->title   = "Dean’s Lister — Certificate & Badge";
            $existing->message = "Your Dean’s Lister certificate is ready. Tap to claim.";
            $existing->data    = $payloadData;
            $existing->claimable = true;
            $existing->save();

            return $existing;
        }

        // Create a fresh one
        return StudentNotification::create([
            'Student_id'  => $app->Student_id,
            'type'        => 'deans_lister_award',
            'title'       => "Dean’s Lister — Certificate & Badge",
            'message'     => "Your Dean’s Lister certificate is ready. Tap to claim.",
            'data'        => $payloadData,
            'claim_token' => Str::uuid()->toString(),
            'claimable'   => true,
            'is_read'     => false,
        ]);
    }

    /**
     * Compose and send the email (claim link if we have a token, else notifications page).
     */
    private function sendApprovedEmail(Application $app, ?StudentNotification $notif = null): void
    {
        try {
            // Student + relations
            $student = StudentManage::with(['login','program','college'])
                ->where('Student_id', $app->Student_id)
                ->first();

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

            $studentNo   = $student?->SRCODE ?? (string)($student?->Student_id ?? $app->Student_id);
            $programName = $student?->program?->Program_name ?? '—';
            $collegeName = $student?->college?->College_name ?? '—';
            $yearLevel   = (string)($app->YearLevel ?? $app->year_level ?? $student?->Year ?? '—');
            $gwaVal      = $app->GWA ?? null;
            $gwaTxt      = is_numeric($gwaVal) ? number_format((float)$gwaVal, 2) : '—';
            $rankTxt     = $app->rank ?? $app->distinction ?? 'Dean’s Lister';
            $termTxt     = $app->term ?? (now()->month <= 5 ? '2nd Semester' : '1st Semester');
            $ayTxt       = $app->ay ?? sprintf('%d-%d', now()->year, now()->addYear()->year);

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

            // Queue or send – choose what you prefer:
            Mail::to($email)->send(new DeansListApprovedMail($payload));
            // Mail::to($email)->queue(new DeansListApprovedMail($payload));

            Log::info('DeansList mail sent', [
                'to' => $email,
                'application_id' => $app->Application_id ?? $app->id,
            ]);
        } catch (\Throwable $e) {
            Log::error('Dean approval email failed', [
                'err' => $e->getMessage(),
                'app_id' => $app->Application_id ?? $app->id,
            ]);
        }
    }
}
