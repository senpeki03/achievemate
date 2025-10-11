<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Models\StudentNotification;
use App\Models\Portfolio;

class AwardClaimController extends Controller
{
    /** Convert absolute path / URL / rel path → clean REL path for public disk */
    private function toPublicRel(?string $value): ?string
    {
        if (!$value) return null;
        $v = str_replace('\\', '/', trim($value));

        if (!Str::startsWith(Str::lower($v), ['http://','https://','/'])) {
            return ltrim($v, '/');
        }

        $path = parse_url($v, PHP_URL_PATH) ?: $v;
        $path = str_replace('\\', '/', $path);

        if (Str::startsWith($path, '/storage/')) {
            return ltrim(Str::after($path, '/storage/'), '/');
        }

        if (is_file($v)) {
            $pubRoot = str_replace('\\','/', rtrim(Storage::disk('public')->path(''), DIRECTORY_SEPARATOR)) . '/';
            if (Str::startsWith(Str::lower($v), Str::lower($pubRoot))) {
                return ltrim(Str::after($v, $pubRoot), '/');
            }
        }

        return ltrim($path, '/');
    }

    public function claim(string $token, Request $request)
    {
        $studentId = (int) (session('Student_id') ?? 0);
        if ($studentId <= 0) {
            return redirect()->route('login')->with('error', 'Please login.');
        }

        $notif = StudentNotification::where('claim_token', $token)
            ->where('Student_id', $studentId)
            ->where('type', 'deans_lister_award')
            ->first();

        if (!$notif) {
            return back()->with('error', 'Invalid or already-claimed award.');
        }
        if (!$notif->claimable || $notif->claimed_at) {
            return redirect()->route('student.notifications')
                ->with('info', 'This award has already been claimed.');
        }

        $data = $notif->data;
        if (is_string($data)) {
            $decoded = json_decode($data, true);
            if (json_last_error() === JSON_ERROR_NONE) $data = $decoded;
        }
        if (!is_array($data)) $data = [];

        $rel = $this->toPublicRel($data['certificate_path'] ?? null)
            ?: $this->toPublicRel($data['certificate_url'] ?? null);

        if (!$rel && !empty($data['application_id'])) {
            $rel = 'cor/cert_' . (int) $data['application_id'] . '.png';
        }

        $badgeRel = $this->toPublicRel($data['badge_path'] ?? '') ?: 'assets/badges/deans_lister.png';

        $portfolio = Portfolio::updateOrCreate(
            ['Student_id' => $studentId, 'type' => 'DeanLister', 'title' => 'Dean’s Lister'],
            ['description' => 'Awarded Dean’s Honor List.', 'badge_path' => $badgeRel, 'certificate_path' => $rel]
        );

        $notif->update(['claimed_at' => now(), 'claimable' => false, 'is_read' => true]);

        Log::info('Award claimed → portfolio updated', [
            'student_id' => $studentId,
            'portfolio_id' => $portfolio->getKey(),
            'cert_rel' => $rel,
            'exists'   => $rel ? Storage::disk('public')->exists($rel) : null,
        ]);

        return redirect()->route('student.portfolio')
            ->with('success', $rel ? 'Certificate added to your portfolio.' : 'Award claimed (no certificate file found).');
    }
}
