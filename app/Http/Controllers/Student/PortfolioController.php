<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Portfolio;
use App\Models\StudentManage;
use App\Models\StudentNotification;
use App\Models\Profile;
use App\Services\CertificateImageService;
use App\Services\DeanCertDataBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PortfolioController extends Controller
{
    /** Normalize to a clean relative path under the public disk. */
    private function normalizeRel(?string $rel): ?string
    {
        if (!$rel) return null;
        $clean = ltrim(str_replace('\\', '/', $rel), '/');

        // Full URL -> strip host; prefer /storage if present
        if (Str::startsWith($clean, ['http://', 'https://'])) {
            $p = parse_url($clean, PHP_URL_PATH) ?: '';
            $p = ltrim(str_replace('\\', '/', $p), '/');
            if (Str::startsWith($p, 'storage/')) {
                return ltrim(Str::after($p, 'storage/'), '/');
            }
            return $p ?: null;
        }

        // asset('storage/...') inputs
        if (Str::startsWith($clean, 'storage/')) {
            return ltrim(Str::after($clean, 'storage/'), '/');
        }

        return $clean ?: null;
    }

    /**
     * Primary preview URL:
     *  - If the file exists on the public disk, return /storage/... (symlink)
     *  - Else, fall back to your /media streaming route
     */
    private function mediaUrl(string $rel): string
    {
        $rel = ltrim($rel, '/');
        $disk = Storage::disk('public');

        if ($rel === '') {
            // safety: huwag tumawag ng route('media') na walang path
            return '#';
        }

        return $disk->exists($rel)
            ? $disk->url($rel)
            : route('media', ['path' => $rel]);
    }

    /** Download URL: prefer /storage if file exists; else fall back to /media */
    private function downloadUrl(string $rel): string
    {
        $rel = ltrim($rel, '/');
        $disk = Storage::disk('public');

        if ($rel === '') {
            return '#';
        }

        return $disk->exists($rel) ? $disk->url($rel) : $this->mediaUrl($rel);
    }

    /** Decide tier from notification payload or Application-like array. */
    private function determineHonorTierFrom(array $data): ?string
    {
        // direct from notif
        $tier = trim((string)($data['honor_tier'] ?? ''));
        if ($tier !== '') return $tier;

        // from rank/distinction text
        $rank = trim((string)($data['rank'] ?? $data['distinction'] ?? ''));
        $l = mb_strtolower($rank);
        if ($l !== '') {
            if (str_contains($l, 'first') || str_contains($l, '1st'))   return 'First Honors';
            if (str_contains($l, 'second')|| str_contains($l, '2nd'))   return 'Second Honors';
        }

        // from numeric GWA
        $gwaStr = (string)($data['gwa'] ?? $data['GWA'] ?? '');
        $gwa = is_numeric($gwaStr) ? (float)$gwaStr : null;
        if ($gwa !== null) {
            if ($gwa <= 1.25) return 'First Honors';
            if ($gwa <= 1.50) return 'Second Honors';
        }
        return null;
    }

    /** Return public-relative badge path for a tier. */
    private function badgeRelForTier(?string $tier): ?string
    {
        if ($tier === 'First Honors')  return 'img/cert/badge_gold.png';
        if ($tier === 'Second Honors') return 'img/cert/badge_silver.png';
        return null;
    }

    /** Normalize to the badge item structure consumed by the Blade. */
    private function makeBadgeItem(?string $tier, ?string $term = null, ?string $ay = null, ?string $explicitRel = null): ?array
    {
        $rel = $explicitRel ?: $this->badgeRelForTier($tier);
        if (!$rel) return null;

        // If explicitRel is a storage-relative path, convert to a public URL properly
        $url = Str::startsWith($rel, ['http://', 'https://', '/'])
            ? $rel
            : asset($rel);

        return [
            'label' => $tier ?: 'Dean’s Lister',
            'img'   => $url, // public/ path
            'meta'  => trim(($term ? $term.' ' : '').($ay ?? '')),
            'key'   => strtolower(($tier ?: 'dl')."|".($term ?: '')."|".($ay ?: '')),
        ];
    }

    /**
     * Fallback avatar as inline SVG (walang 404 kahit wala kang actual PNG sa public/img).
     */
    private function defaultAvatar(): string
    {
        $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="160" height="200">
  <rect width="100%" height="100%" fill="#7a0000"/>
  <text x="50%" y="52%" fill="#ffffff" font-size="56" font-family="system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif" text-anchor="middle" dominant-baseline="middle">👤</text>
</svg>
SVG;
        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    /**
     * Resolve profile photo:
     *  - If `profile.Profile` contains raw BLOB image ➜ convert to data:image/...;base64,...
     *  - If it looks like a URL/path ➜ resolve normally (public disk or media route)
     *  - Else ➜ default inline avatar (no 404)
     */
    private function resolveProfilePhoto(int $studentId): string
    {
        $row = Profile::where('Student_id', $studentId)->first();
        if (!$row || $row->Profile === null) {
            return $this->defaultAvatar();
        }

        $val = $row->Profile;

        // If resource (stream), basahin as string
        if (is_resource($val)) {
            $val = stream_get_contents($val);
        }

        // If wala pa ring string, fallback
        if (!is_string($val)) {
            return $this->defaultAvatar();
        }

        // Try to see if this is already a data URL
        if (Str::startsWith($val, 'data:image/')) {
            return $val;
        }

        // If mukhang HTTP URL or path string (walang binary chars, may slash / extension)
        $printable = preg_replace('/[[:^print:]]/', '', $val);
        $looksLikePath = preg_match('~\.(png|jpe?g|gif|webp)$~i', $printable)
            && (str_contains($printable, '/') || str_contains($printable, '\\'));

        if (preg_match('~^https?://~i', $printable)) {
            // already full URL
            return $printable;
        } elseif ($looksLikePath) {
            // treat as relative path saved in DB
            $rel = $this->normalizeRel($printable);
            if ($rel) {
                $disk = Storage::disk('public');
                if ($disk->exists($rel)) {
                    return $disk->url($rel);
                }
                return $this->mediaUrl($rel);
            }
        }

        // At this point, assume raw binary image (BLOB)
        $binary = $val;

        // Detect mime by magic bytes (simple heuristics)
        $mime = 'image/jpeg';
        if (str_starts_with($binary, "\x89PNG")) {
            $mime = 'image/png';
        } elseif (str_starts_with($binary, "GIF87a") || str_starts_with($binary, "GIF89a")) {
            $mime = 'image/gif';
        }

        return 'data:' . $mime . ';base64,' . base64_encode($binary);
    }

    public function index(CertificateImageService $certSvc)
    {
        $studentId = (int) (
            session('Student_id')
            ?? session('studentID')
            ?? optional(auth()->user())->Student_id
            ?? 0
        );

        if ($studentId <= 0) {
            return view('student.portfolio', [
                'deansCertificates' => collect(),
                'badges'            => collect(),
                'studentName'       => null,
                'studentProgram'    => null,
                'studentYearLevel'  => null,
                'achievements'      => collect(),
                'student'           => null,
                'course'            => null,
                'semesterLabel'     => null,
                'enrollmentStatus'  => null,
                'photoUrl'          => $this->defaultAvatar(),
            ]);
        }

        /* ----------------- Header context ----------------- */
        $student = StudentManage::with([
                'curriculum.curriculumAy.program',
                'studentCourse.campus',
                'studentCourse.college',
                'studentCourse.program',
                'studentCourse.major',
            ])
            ->where('Student_id', $studentId)
            ->first();

        // Primary course row (assuming one active course)
        $course = $student?->studentCourse?->first();

        // NAME: LAST, FIRST M.
        if ($student) {
            $midInitial = $student->Middle_name
                ? ' ' . strtoupper(substr($student->Middle_name, 0, 1)) . '.'
                : '';
            $studentName = strtoupper("{$student->Last_name}, {$student->First_name}{$midInitial}");
        } else {
            $studentName = null;
        }

        // Prefer program from StudentCourse, fallback to old curriculum-based logic
        $programName =
            $course?->program?->Program_name
            ?? optional(optional(optional($student)->curriculum)->curriculumAy)->program->Program_name
            ?? ($student->Program_name ?? $student->Course ?? null);

        $studentProgram   = $programName ?? '—';
        $studentYearLevel = $student->Year ?? '—';

        // For the hero header (string para madali i-display)
        $semesterLabel    = 'FIRST SEMESTER AY ' . ($student->Academic_year ?? '—');
        $enrollmentStatus = 'ENROLLED'; // TODO: make dynamic if you add a status column later

        // ✅ Resolve profile photo from `profile` table (BLOB or path)
        $photoUrl = $this->resolveProfilePhoto($studentId);

        /* ====================== 1) From notifications ====================== */
        $notifRows = StudentNotification::where('Student_id', $studentId)
            ->where('type', 'deans_lister_award')
            ->orderByDesc('StudentNotification_id')
            ->get();

        $deansFromNotifs = $notifRows->map(function ($n) {
            $data = is_array($n->data) ? $n->data : (json_decode($n->data ?? '[]', true) ?: []);
            $rel  = $this->normalizeRel($data['certificate_path'] ?? null)
                 ?: $this->normalizeRel($data['certificate_url'] ?? null);

            if (!$rel) return null;

            $url      = $this->mediaUrl($rel);       // storage-first
            $download = $this->downloadUrl($rel);    // storage-first
            $isImage  = (bool) preg_match('/\.(png|jpe?g|webp)$/i', $rel);
            $finger   = Str::of($rel)->lower()->toString();

            return [
                'title'        => 'Dean’s Lister',
                'semester'     => $data['semester']    ?? ($data['term'] ?? ''),
                'school_year'  => $data['school_year'] ?? ($data['ay']   ?? ''),
                'gwa'          => $data['gwa']         ?? null,
                'rank'         => $data['rank']        ?? ($data['honor_tier'] ?? null),
                'preview_url'  => $url,
                'download_url' => $download ?: $url,
                'thumbnail'    => ($isImage && $url) ? $url : null,
                'is_image'     => $isImage,
                'fingerprint'  => $finger,
            ];
        })->filter(fn($x) => !empty($x['preview_url']))->values();

        /* ====================== 2) From portfolio (permissive) ====================== */
        $portfolio = Portfolio::where('Student_id', $studentId)
            ->orderByDesc('Portfolio_id')
            ->get(['badge_path','certificate_path','title','description']);

        // BADGES (rank-aware)
        $badgeFromNotifs = $notifRows->map(function ($n) {
            $data = is_array($n->data) ? $n->data : (json_decode($n->data ?? '[]', true) ?: []);
            $tier = $this->determineHonorTierFrom($data);
            $explicitRel = null;

            if (!empty($data['badge_path']) && is_string($data['badge_path'])) {
                $explicitRel = ltrim(str_replace('\\','/',$data['badge_path']),'/');
            }

            $term = $data['term'] ?? $data['semester'] ?? null;
            $ay   = $data['ay']   ?? $data['school_year'] ?? null;

            return $this->makeBadgeItem($tier, $term, $ay, $explicitRel);
        })->filter()->values();

        $badgeFromPortfolio = $portfolio->whereNotNull('badge_path')->map(function ($p) {
            $rel = $this->normalizeRel((string) $p->badge_path);
            if (!$rel) return null;

            $name = strtolower(basename($rel));
            $tier = str_contains($name,'gold') ? 'First Honors' :
                    (str_contains($name,'silver') ? 'Second Honors' : 'Dean’s Lister');

            return $this->makeBadgeItem($tier, null, null, $rel);
        })->filter()->values();

        $badgeItems = $badgeFromNotifs->concat($badgeFromPortfolio);

        /* ====================== Certificates from portfolio ====================== */
        $deansFromPortfolio = $portfolio->whereNotNull('certificate_path')->map(function ($p) {
            $rel = $this->normalizeRel((string) $p->certificate_path);
            if (!$rel) return null;

            if (Str::startsWith(Str::lower($rel), ['http://','https://'])) {
                $url = $rel;
                $dl  = $rel;
                $isImage = (bool) preg_match('/\.(png|jpe?g|webp)$/i', parse_url($rel, PHP_URL_PATH) ?? '');
            } else {
                $url = $this->mediaUrl($rel);     // storage-first
                $dl  = $this->downloadUrl($rel);  // storage-first
                $isImage = (bool) preg_match('/\.(png|jpe?g|webp)$/i', $rel);
            }

            return [
                'title'        => $p->title ?: 'Dean’s Lister',
                'semester'     => '',
                'school_year'  => '',
                'gwa'          => null,
                'rank'         => null,
                'preview_url'  => $url,
                'download_url' => $dl,
                'thumbnail'    => $isImage ? $url : null,
                'is_image'     => $isImage,
                'fingerprint'  => Str::of($rel ?: (parse_url($url, PHP_URL_PATH) ?? ''))
                                    ->lower()->replace('/storage/','')->trim('/')->toString(),
            ];
        })->filter()->values();

        /* ====================== 3) From applications (fallback) ====================== */
        $hasUpdatedAt = Schema::hasColumn('application', 'updated_at');
        $hasCreatedAt = Schema::hasColumn('application', 'created_at');

        $hasType      = Schema::hasColumn('application', 'Type');
        $hasAwardType = Schema::hasColumn('application', 'award_type');
        $hasCategory  = Schema::hasColumn('application', 'category');
        $hasAppType   = Schema::hasColumn('application', 'application_type');
        $hasGwaLower  = Schema::hasColumn('application', 'gwa');
        $hasGwaUpper  = Schema::hasColumn('application', 'GWA');
        $hasRankLower = Schema::hasColumn('application', 'rank');
        $hasRankUpper = Schema::hasColumn('application', 'Rank');

        $q = Application::query()
            ->with(['student.curriculum.curriculumAy.program.college.campus'])
            ->where('Student_id', $studentId)
            ->where(function ($w) {
                $w->whereRaw('LOWER(COALESCE(status,"")) = ?', ['approved'])
                  ->orWhere('status', 1);
            });

        if ($hasType || $hasAwardType || $hasCategory || $hasAppType) {
            $q->where(function ($w) use ($hasType, $hasAwardType, $hasCategory, $hasAppType) {
                $vals = "('dean''s list','deans list','deans_list','dean')";
                if ($hasType)      $w->orWhereRaw("LOWER(COALESCE(`Type`,'')) IN $vals");
                if ($hasAwardType) $w->orWhereRaw("LOWER(COALESCE(`award_type`,'')) IN $vals");
                if ($hasCategory)  $w->orWhereRaw("LOWER(COALESCE(`category`,'')) IN $vals");
                if ($hasAppType)   $w->orWhereRaw("LOWER(COALESCE(`application_type`,'')) IN $vals");
            });
        }

        if ($hasUpdatedAt || $hasCreatedAt) {
            $q->orderByDesc(DB::raw("COALESCE(" .
                ($hasUpdatedAt ? 'updated_at' : 'NULL') . ',' .
                ($hasCreatedAt ? 'created_at' : 'NULL') . ')'));
        }

        $select = ['Application_id','Student_id','status'];
        if ($hasType)      $select[] = 'Type';
        if ($hasAwardType) $select[] = 'award_type';
        if ($hasCategory)  $select[] = 'category';
        if ($hasAppType)   $select[] = 'application_type';
        if ($hasGwaLower)  $select[] = 'gwa';
        if ($hasGwaUpper)  $select[] = 'GWA';
        if ($hasRankLower) $select[] = 'rank';
        if ($hasRankUpper) $select[] = 'Rank';
        if ($hasUpdatedAt) $select[] = 'updated_at';
        if ($hasCreatedAt) $select[] = 'created_at';

        $apps = $q->get($select);

        if ($badgeItems->isEmpty()) {
            $latestApp = $apps->first();
            if ($latestApp) {
                $tier = $this->determineHonorTierFrom([
                    'rank' => $latestApp->getAttribute('rank') ?? $latestApp->getAttribute('Rank'),
                    'gwa'  => $latestApp->getAttribute('GWA')  ?? $latestApp->getAttribute('gwa'),
                ]);
                $badge = $this->makeBadgeItem($tier, $latestApp->term ?? null, $latestApp->ay ?? null);
                if ($badge) $badgeItems = collect([$badge]);
            }
        }

        $deansFromApps = $apps->map(function ($app) use ($certSvc, $hasUpdatedAt, $hasCreatedAt)  {

            $builderData = app(DeanCertDataBuilder::class)->buildFromStudent(
                $app->Student_id,
                [
                    'template_png' => public_path('img/cert/dean-template.png'),
                    'out_rel'      => "cor/cert_{$app->Application_id}.png",
                    'app_id'       => $app->Application_id,
                ]
            );

            $approvedAt  = ($hasUpdatedAt ? $app->updated_at : null)
                        ?? ($hasCreatedAt ? $app->created_at : null);
            if ($approvedAt) {
                $builderData['date_conferred'] = \Illuminate\Support\Carbon::parse($approvedAt)->format('F d, Y');
            }

            $rel    = $certSvc->makeDeansCertPng($builderData);
            $pngRel = $rel ?: "cor/cert_{$app->Application_id}.png";

            $urlPng = $this->mediaUrl($pngRel);     // storage-first
            $dlPng  = $this->downloadUrl($pngRel);  // storage-first

            return [
                'title'        => "Dean’s Lister",
                'semester'     => $builderData['semester'] ?? '',
                'school_year'  => $builderData['school_year'] ?? '',
                'gwa'          => $builderData['gwa'] ?? null,
                'rank'         => $app->getAttribute('rank') ?? $app->getAttribute('Rank') ?? null,
                'preview_url'  => $urlPng,
                'download_url' => $dlPng,
                'thumbnail'    => $urlPng,
                'is_image'     => true,
                'fingerprint'  => Str::of($pngRel)->lower()->trim('/')->toString(),
            ];
        });

        /* ====================== Merge + DEDUPE ====================== */
        $deansCertificates = $deansFromNotifs
            ->concat($deansFromPortfolio)
            ->concat($deansFromApps)
            ->filter()
            ->unique(function ($item) {
                $fp = trim(strtolower($item['fingerprint'] ?? ''));
                if ($fp !== '') return $fp;

                $t  = trim(strtolower($item['title'] ?? ''));
                $sm = trim(strtolower($item['semester'] ?? ''));
                $sy = trim(strtolower($item['school_year'] ?? ''));
                return $t.'|'.$sm.'|'.$sy;
            })
            ->values()
            ->map(function ($x) { unset($x['fingerprint']); return $x; });

        $badgeItems = $badgeItems
            ->unique('key')
            ->map(function ($x){ unset($x['key']); return $x; })
            ->values();

        return view('student.portfolio', [
            'deansCertificates' => $deansCertificates,
            'badges'            => $badgeItems,
            'studentName'       => $studentName,
            'studentProgram'    => $studentProgram,
            'studentYearLevel'  => $studentYearLevel,
            'achievements'      => collect(),

            'student'           => $student,
            'course'            => $course,
            'semesterLabel'     => $semesterLabel,
            'enrollmentStatus'  => $enrollmentStatus,
            'photoUrl'          => $photoUrl,
        ]);
    }
}
