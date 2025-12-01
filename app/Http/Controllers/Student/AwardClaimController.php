<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Models\StudentNotification;
use App\Models\Portfolio;

class AwardClaimController extends Controller
{
    /**
     * Convert absolute/URL/rel to *relative* path on the public disk.
     */
    private function toPublicRel(?string $value): ?string
    {
        if (!$value) return null;
        $v = str_replace('\\', '/', trim($value));

        // already a relative path like "certificates/....png"
        if (!preg_match('~^(?:https?://|/)~i', $v)) {
            return ltrim($v, '/');
        }

        // handle "/storage/..." URLs
        $path = parse_url($v, PHP_URL_PATH) ?: $v;
        $path = str_replace('\\', '/', $path);
        if (str_starts_with($path, '/storage/')) {
            return ltrim(substr($path, 9), '/'); // strip "/storage/"
        }

        // absolute file path under storage/app/public
        $pubRoot = str_replace('\\', '/', rtrim(Storage::disk('public')->path(''), DIRECTORY_SEPARATOR)) . '/';
        if (str_starts_with(strtolower($v), strtolower($pubRoot))) {
            return ltrim(substr($v, strlen($pubRoot)), '/');
        }

        return ltrim($path, '/');
    }

    /**
     * Optional: list award notifications for the logged-in student.
     */
    public function index(Request $request)
    {
        $studentId = (int) (session('Student_id') ?? 0);
        if ($studentId <= 0) {
            return redirect()->route('login')->with('error', 'Please login.');
        }

        $awards = StudentNotification::where('Student_id', $studentId)
            ->where('type', 'deans_lister_award') // the award we create on Dean approval
            ->latest('StudentNotification_id')
            ->get();

        return view('student.notifications', compact('awards'));
    }

    /**
     * Claim an award by token → mark claimed + push to portfolio + show/download the cert.
     */
    public function claim(string $token, Request $request)
    {
        Log::info('AwardClaim DEBUG: entered', ['token' => $token]);

        // Find notification by token only
        $notif = StudentNotification::where('claim_token', $token)
            ->whereIn('type', ['deans_lister_award', 'award.deans_lister'])
            ->first();

        if (!$notif) {
            Log::info('AwardClaim DEBUG: invalid token', ['token' => $token]);

            return redirect('https://achievemate.website/student/notifications')
                ->with('error', 'Invalid or already claimed award.');
        }

        // Student ID from notification
        $studentId = (int) $notif->Student_id;

        // ✅ IMPORTANT: set Student_id sa session para hindi ka i-redirect ng notifications controller sa /login
        session(['Student_id' => $studentId]);

        // Decode notif data
        $data = is_string($notif->data) ? json_decode($notif->data, true) : $notif->data;
        if (!is_array($data)) {
            $data = [];
        }

        // Determine certificate path (relative, and must exist on public disk)
        $certRel = $data['certificate_path'] ?? null;
        $certRel = $this->toPublicRel($certRel);

        if (!$certRel || !Storage::disk('public')->exists($certRel)) {
            $certRel = 'certificates/deans_lister_placeholder.png';
        }

        // Mark as claimed
        $notif->update([
            'claimed_at' => now(),
            'claimable'  => false,
            'is_read'    => true,
        ]);

        // Add/Update in portfolio
        Portfolio::updateOrCreate(
            ['Student_id' => $studentId, 'type' => 'DeanLister'],
            [
                'title'            => 'Dean’s Lister',
                'description'      => 'Awarded to the student.',
                'certificate_path' => $certRel,
                'badge_path'       => 'assets/badges/deans_lister.png',
            ]
        );

        Log::info('AwardClaim DEBUG: claimed + portfolio updated', [
            'student_id' => $studentId,
            'certRel'    => $certRel,
        ]);

        // FINAL REDIRECT → notifications page
        return redirect('https://achievemate.website/student/notifications')
            ->with('claim_success', [
                'title'   => 'Congratulations!',
                'message' => 'Your certificate has been added to your Portfolio.',
            ])
            ->with('claimed_cert_url', Storage::url($certRel));
    }
}
