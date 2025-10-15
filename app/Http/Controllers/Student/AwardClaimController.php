<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
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
        $pubRoot = str_replace('\\','/', rtrim(Storage::disk('public')->path(''), DIRECTORY_SEPARATOR)).'/';
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
            ->where('type', 'deans_lister_award')         // the award we create on Dean approval
            ->latest('StudentNotification_id')
            ->get();

        return view('student.notifications', compact('awards'));
    }

    /**
     * Claim an award by token → mark claimed + push to portfolio + show/download the cert.
     */
    public function claim(string $token, Request $request)
    {
        $studentId = (int) (session('Student_id') ?? 0);
        if ($studentId <= 0) {
            return redirect()->route('login')->with('error', 'Please login.');
        }

        $notif = StudentNotification::where('claim_token', $token)
            ->where('Student_id', $studentId)
            ->whereIn('type', ['deans_lister_award', 'award.deans_lister'])
            ->first();

        if (!$notif) {
            return redirect()->route('student.notifications')
                ->with('error', 'Invalid or already-claimed award.');
        }

        if (!$notif->claimable || $notif->claimed_at) {
            return redirect()->route('student.notifications')
                ->with('info', 'This award has already been claimed.');
        }

        // decode json flexibly
        $data = $notif->data;
        if (is_string($data)) {
            $d = json_decode($data, true);
            if (json_last_error() === JSON_ERROR_NONE) $data = $d;
        }
        if (!is_array($data)) $data = [];

        // paths (relative to public disk)
        $certRel  = $this->toPublicRel($data['certificate_path'] ?? null)
                ?: $this->toPublicRel($data['certificate_url']  ?? null);
        $badgeRel = $this->toPublicRel($data['badge_path']       ?? null) ?: 'assets/badges/deans_lister.png';

        // If file missing OR it’s the placeholder → regenerate a personalized PNG
        $isPlaceholder = $certRel && str_contains($certRel, 'deans_lister_placeholder');
        if (!$certRel || !Storage::disk('public')->exists($certRel) || $isPlaceholder) {
            $appId = (int) ($data['application_id'] ?? 0);

            if ($appId > 0) {
                try {
                    // Build the payload and generate a fresh PNG
                    $payload = app(\App\Services\DeanCertDataBuilder::class)->buildFromStudent(
                        $studentId,
                        [
                            'template_png' => public_path('img/cert/dean-template.png'),
                            'out_rel'      => "certificates/deans_lister_app_{$appId}.png",
                            'app_id'       => $appId,
                        ]
                    );

                    $newRel = app(\App\Services\CertificateImageService::class)->makeDeansCertPng($payload);

                    if ($newRel && Storage::disk('public')->exists($newRel)) {
                        $certRel = $newRel;
                        // persist back to notif.data
                        $data['certificate_path'] = $newRel;
                        $notif->update(['data' => $data]);
                    }
                } catch (\Throwable $e) {
                    \Log::error('Claim regenerate failed', ['err' => $e->getMessage(), 'app_id' => $appId, 'notif' => $notif->StudentNotification_id ?? null]);
                }
            }

            // If still nothing, make sure a PNG placeholder exists
            if (!$certRel || !Storage::disk('public')->exists($certRel)) {
                $certRel = 'certificates/deans_lister_placeholder.png';
                if (!Storage::disk('public')->exists($certRel)) {
                    $tpl = public_path('img/cert/dean-template.png');
                    Storage::disk('public')->put($certRel, is_file($tpl) ? file_get_contents($tpl) : '');
                }
                // Also store placeholder path so UI has something stable
                $data['certificate_path'] = $certRel;
                $notif->update(['data' => $data]);
            }
        }

        // Mark as claimed
        $notif->update([
            'claimed_at' => now(),
            'claimable'  => false,
            'is_read'    => true,
        ]);

        // Optional: keep portfolio in sync
        if (class_exists(Portfolio::class)) {
            Portfolio::updateOrCreate(
                ['Student_id' => $studentId, 'type' => 'DeanLister', 'title' => 'Dean’s Lister'],
                [
                    'description'      => 'Awarded Dean’s Honor List.',
                    'badge_path'       => $badgeRel,
                    'certificate_path' => $certRel,
                ]
            );
        }

        // Serve the certificate image
        $abs = Storage::disk('public')->path($certRel);
        // Success path — redirect with flash for the modal
        return redirect()
            ->route('student.portfolio') // or ->route('student.notifications')
            ->with('claim_success', [
                'title'   => 'Congratulations! 🎉',
                'message' => 'Keep up the good work! Your certificate has been added to your Portfolio.',
            ])
            ->with('claimed_cert_url', Storage::url($certRel)); // optional "View Certificate" button

    }

}
