<?php
// app/Observers/ApplicationObserver.php
namespace App\Observers;

use App\Models\Application;
use App\Models\StudentManage;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Mail\DeansListApprovedMail;

class ApplicationObserver
{
    public function updated(Application $app): void
    {
        // only when status transitions to Approved
        $was = $app->getOriginal('Status');
        $now = $app->Status;
        if ($was === 'Approved' || $now !== 'Approved') return;

        try {
            // load student + login + program/college for email + details
            $student = StudentManage::with(['login','program.college'])
                ->where('Student_id', $app->Student_id)
                ->first();

            // username in `login` table is the email
            $email = $student?->login?->username;  // <— THIS is your email

            // safety fallbacks if you also mirror email on StudentManage
            $email = $email ?? $student?->username ?? $student?->email ?? null;

            if (!$email) {
                Log::warning('DeansList mail: missing login email', [
                    'student_id' => $app->Student_id,
                    'application_id' => $app->Application_id ?? $app->id,
                ]);
                return;
            }

            // ——— Build data for your existing Mailable ———
            $studentName = $student?->Fullname
                         ?? $student?->full_name
                         ?? trim(($student?->FirstName ?? '').' '.($student?->LastName ?? ''))
                         ?: 'Student';

            $studentNo   = $student?->Student_no
                         ?? $student?->student_no
                         ?? (string)($student?->Student_id ?? $app->Student_id);

            $programName = $student?->program?->Program_name
                         ?? $student?->program_name
                         ?? '—';

            $collegeName = $student?->program?->college?->College_name
                         ?? $student?->college?->College_name
                         ?? '—';

            $yearLevel   = (string)($app->YearLevel ?? $app->year_level ?? $student?->YearLevel ?? '—');

            $gwaVal      = $app->GWA ?? $app->gwa ?? null;
            $gwaTxt      = is_numeric($gwaVal) ? number_format((float)$gwaVal, 2) : '—';

            $rankTxt     = $app->rank ?? $app->distinction ?? 'Dean’s Lister';
            $termTxt     = $app->term ?? (now()->month <= 5 ? '2nd Semester' : '1st Semester');
            $ayTxt       = $app->ay   ?? sprintf('%d-%d', now()->year, now()->addYear()->year);

            $downloadLink = route('dean.certificate.download', [
                'application' => $app->Application_id ?? $app->id,
            ]);

            $data = [
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
                'deanName'           => config('app.dean_name', 'Dean'), // don’t rely on auth() in observers
                'deanTitle'          => config('app.dean_title', 'Dean'),
                'systemName'         => config('app.name'),
                'supportEmail'       => config('mail.from.address'),
            ];

            Mail::to($email)->send(new DeansListApprovedMail($data));

            Log::info('DeansList mail sent', [
                'to' => $email,
                'application_id' => $app->Application_id ?? $app->id
            ]);
        } catch (\Throwable $e) {
            Log::error('Email send failed', [
                'app_id' => $app->Application_id ?? $app->id,
                'err'    => $e->getMessage()
            ]);
        }
    }
}
