<?php

namespace App\Http\Controllers\Dean;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

// ⬇️ add these:
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

use App\Models\Application;
use App\Models\StudentManage;
use App\Models\StudentNotification;

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
            try { $this->awardService->onApproved($app); }
            catch (\Throwable $e) { Log::warning('AwardService onApproved failed', ['e'=>$e->getMessage(), 'app'=>$app->Application_id]); }

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

                try { $this->awardService->onApproved($app); }
                catch (\Throwable $e) { Log::warning('AwardService onApproved failed', ['e'=>$e->getMessage(), 'app'=>$app->Application_id]); }

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

    private function sendApprovedEmail(Application $app): void
    {
        try {
            $student = StudentManage::with(['login','program','college'])
                ->where('Student_id', $app->Student_id)
                ->first();

            $email = $student?->login?->username
                  ?? $student?->Email
                  ?? null;

            if (!$email) {
                Log::warning('Dean approval: no email found', ['student_id'=>$app->Student_id, 'app'=>$app->Application_id]);
                return;
            }

            $studentName = trim(implode(' ', array_filter([
                $student?->First_name, $student?->Middle_name, $student?->Last_name,
            ]))) ?: 'Student';

            $studentNo   = $student?->SRCODE ?? (string)($student?->Student_id ?? $app->Student_id);
            $programName = $student?->program?->Program_name ?? '—';
            $collegeName = $student?->college?->College_name ?? '—';
            $yearLevel   = (string)($app->YearLevel ?? $student?->Year ?? '—');
            $gwaVal      = $app->GWA ?? null;
            $gwaTxt      = is_numeric($gwaVal) ? number_format((float)$gwaVal, 2) : '—';
            $rankTxt     = $app->rank ?? $app->distinction ?? 'Dean’s Lister';
            $termTxt     = $app->term ?? (now()->month <= 5 ? '2nd Semester' : '1st Semester');
            $ayTxt       = $app->ay ?? sprintf('%d-%d', now()->year, now()->addYear()->year);

            // send them to the in-app notifications page
            $downloadLink = Route::has('student.notifications')
                ? route('student.notifications')
                : url('/student/notifications');

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
            Log::info('DeansList mail sent', ['to'=>$email, 'app'=>$app->Application_id]);
        } catch (\Throwable $e) {
            Log::error('Dean approval email failed', ['err'=>$e->getMessage(), 'app'=>$app->Application_id]);
        }
    }

    private function createAwardNotification(Application $app): void
    {
        try {
            // ensure there is a PNG certificate asset available
            $relCert = 'certificates/deans_lister_placeholder.png';
            if (!Storage::disk('public')->exists($relCert)) {
                // generate from your PNG template if you want, otherwise drop a placeholder image
                Storage::disk('public')->put($relCert, file_get_contents(public_path('img/cert/dean-template.png')));
            }

            // upsert: one active claimable per app/student
            $existing = StudentNotification::where('Student_id',$app->Student_id)
                ->where('type','deans_lister_award')
                ->whereNull('claimed_at')
                ->first();

            if ($existing) {
                $existing->update([
                    'title'    => "Dean’s Lister — Certificate & Badge",
                    'message'  => "Your Dean’s Lister certificate is ready. Tap to claim.",
                    'data'     => [
                        'application_id'   => $app->Application_id,
                        'certificate_path' => $relCert,
                        'badge_path'       => 'assets/badges/deans_lister.png',
                    ],
                    'claimable'=> true,
                    'is_read'  => false,
                ]);
            } else {
                StudentNotification::create([
                    'Student_id' => $app->Student_id,
                    'type'       => 'deans_lister_award',
                    'title'      => "Dean’s Lister — Certificate & Badge",
                    'message'    => "Your Dean’s Lister certificate is ready. Tap to claim.",
                    'data'       => [
                        'application_id'   => $app->Application_id,
                        'certificate_path' => $relCert,
                        'badge_path'       => 'assets/badges/deans_lister.png',
                    ],
                    'claim_token' => Str::uuid()->toString(),
                    'claimable'   => true,
                    'is_read'     => false,
                ]);
            }

            Log::info('Created/updated award notification', [
                'student_id'=>$app->Student_id,
                'application'=>$app->Application_id,
            ]);
        } catch (\Throwable $e) {
            Log::error('createAwardNotification failed', ['err'=>$e->getMessage()]);
        }
    }
}
