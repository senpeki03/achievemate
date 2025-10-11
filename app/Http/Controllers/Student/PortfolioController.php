<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Portfolio;
use App\Models\StudentManage;
use App\Models\StudentNotification;
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
        return $disk->exists($rel)
            ? $disk->url($rel)
            : route('media', ['path' => $rel]);
    }

    /** Download URL: prefer /storage if file exists; else fall back to /media */
    private function downloadUrl(string $rel): string
    {
        $rel = ltrim($rel, '/');
        $disk = Storage::disk('public');
        return $disk->exists($rel) ? $disk->url($rel) : $this->mediaUrl($rel);
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
            ]);
        }

        /* ----------------- Header context ----------------- */
        $student = StudentManage::with(['curriculum.curriculumAy.program'])
            ->where('Student_id', $studentId)->first();

        $studentName = $student
            ? trim(($student->First_name ?? '').' '.($student->Middle_name ?? '').' '.($student->Last_name ?? ''))
            : null;

        $programName = optional(optional(optional($student)->curriculum)->curriculumAy)->program->Program_name
            ?? ($student->Program_name ?? $student->Course ?? null);

        $studentProgram   = $programName ?? '—';
        $studentYearLevel = $student->Year ?? '—';

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
                'semester'     => $data['semester']    ?? '',
                'school_year'  => $data['school_year'] ?? '',
                'gwa'          => $data['gwa']         ?? null,
                'rank'         => $data['rank']        ?? null,
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

        $badges = $portfolio->whereNotNull('badge_path')->map(function ($p) {
            $rel = $this->normalizeRel((string) $p->badge_path);
            if (!$rel) return null;

            if (Str::startsWith(Str::lower($rel), ['http://','https://'])) {
                return ['icon' => $rel];
            }

            $disk = Storage::disk('public');
            if ($disk->exists($rel)) {
                return ['icon' => $this->mediaUrl($rel)]; // storage-first
            }

            return ['icon' => asset('img/badges/deans_lister.png')];
        })->filter()->values();

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

        // Only select existing columns
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

        // ✅ Generate PNGs using the same builder as Tinker (ensures Program/AY)
        $deansFromApps = $apps->map(function ($app) use ($certSvc, $hasUpdatedAt, $hasCreatedAt)  {

        $builderData = app(\App\Services\DeanCertDataBuilder::class)->buildFromStudent(
            $app->Student_id,
            [
                'template_png' => public_path('img/cert/dean-template.png'),
                'out_rel'      => "cor/cert_{$app->Application_id}.png",
                'app_id'       => $app->Application_id,
            ]
        );

        // Optional: use app timestamps for date conferred
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
            'fingerprint'  => \Illuminate\Support\Str::of($pngRel)->lower()->trim('/')->toString(),
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

        return view('student.portfolio', [
            'deansCertificates' => $deansCertificates,
            'badges'            => $badges,
            'studentName'       => $studentName,
            'studentProgram'    => $studentProgram,
            'studentYearLevel'  => $studentYearLevel,
            'achievements'      => collect(),
        ]);
    }
}
