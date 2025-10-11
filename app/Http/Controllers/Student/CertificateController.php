<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Application;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Schema;
// services
use App\Services\DeanCertDataBuilder;
use App\Services\CertificateImageService;

class CertificateController extends Controller
{
    /**
     * Build the data array consumed by the PDF view.
     */
    protected function buildCertificateData(Application $app): array
    {
        $student = \App\Models\StudentManage::with(['curriculum.curriculumAy.program'])
            ->where('Student_id', $app->Student_id)
            ->firstOrFail();

        // Name
        $first  = trim((string)($student->First_name ?? ''));
        $middle = trim((string)($student->Middle_name ?? ''));
        $last   = trim((string)($student->Last_name ?? ''));
        $name   = trim($first.' '.($middle !== '' ? mb_strtoupper(mb_substr($middle,0,1)).'. ' : '').$last) ?: 'STUDENT NAME';

        // Program & AY (correct casing!)
        $programFromChain = $student->curriculum?->curriculumAy?->program;
        $programName = $programFromChain->Program_name
            ?? $student->Program_name
            ?? $student->Course
            ?? null;

        $rawAy = $student->curriculum?->curriculumAy?->Academic_year
            ?? $app->school_year
            ?? $student->School_year
            ?? '—';

        // Degree line (avoid duplicating leading text)
        $degreeLine = null;
        if (!empty($programName)) {
            $pn = preg_replace('/^Bachelor\s+of\s+Science\s+in\s*/i', '', trim($programName));
            $degreeLine = 'Bachelor of Science in '.$pn;
        }

        // Semester
        $rawSemester = $app->semester
            ?? $student->Semester
            ?? $student->curriculum?->curriculumAy?->Semester
            ?? 'First Semester';
        $semester = $this->prettySemester($rawSemester);

        // AY normalize
        $schoolYear = $this->normalizeAy((string)$rawAy);

        // GWA & rank
        $gwa = $app->gwa ?? $student->GWA ?? null;
        $gwaFormatted = $gwa !== null && $gwa !== '' ? number_format((float)$gwa, 4) : null;

        $tier = $app->honor_tier ?? $app->rank_tier ?? null;
        if (!$tier && $gwa !== null && $gwa !== '') {
            $g = (float)$gwa;
            $tier = $g <= 1.75 ? 'First Honors' : ($g <= 2.00 ? 'Second Honors' : null);
        }

        $deanName  = config('school.dean_name', 'Prof. LORISSA JOANA E. BUENAS, DTech');
        $deanTitle = config('school.dean_title', 'Dean, College of Informatics and Computing Sciences');
        $dateConferred = $app->date_conferred ?? now()->toFormattedDateString();

        return [
            'student_name'       => $name,
            'degree_line'        => $degreeLine,
            'semester'           => $semester,
            'school_year'        => $schoolYear,
            'gwa'                => $gwaFormatted,
            'honor_tier'         => $tier,
            'date_conferred'     => $dateConferred,
            'dean_name'          => $deanName,
            'dean_title'         => $deanTitle,
            'pronoun_possessive' => 'their',
        ];
    }

    /**
     * Enforce approval + optional type constraint.
     */
    protected function mustBeApproved(Application $app): void
    {
        $raw  = $app->getAttribute('status');
        $norm = is_string($raw) ? strtolower(trim($raw)) : $raw;

        $approved = false;
        if (is_numeric($norm)) {
            $approved = ((int)$norm) >= 1;
        } elseif (is_bool($norm)) {
            $approved = $norm === true;
        } else {
            $approved = in_array($norm, ['approved', 'approve', 'ok', 'yes'], true);
        }
        if (!$approved) {
            abort(403, 'Certificate is available only for approved applications.');
        }

        // Type/category enforcement only if columns exist and value is present
        $hasTypeCols = [
            'Type'             => Schema::hasColumn('application', 'Type'),
            'award_type'       => Schema::hasColumn('application', 'award_type'),
            'category'         => Schema::hasColumn('application', 'category'),
            'application_type' => Schema::hasColumn('application', 'application_type'),
        ];
        $hasAnyTypeCol = in_array(true, $hasTypeCols, true);

        if ($hasAnyTypeCol) {
            $vals = ["dean's list", 'deans list', 'deans_list', 'dean'];
            $typeCandidates = [
                is_string($app->getAttribute('Type'))             ? strtolower(trim($app->getAttribute('Type')))             : null,
                is_string($app->getAttribute('award_type'))       ? strtolower(trim($app->getAttribute('award_type')))       : null,
                is_string($app->getAttribute('category'))         ? strtolower(trim($app->getAttribute('category')))         : null,
                is_string($app->getAttribute('application_type')) ? strtolower(trim($app->getAttribute('application_type'))) : null,
            ];

            $nonEmpty = array_filter($typeCandidates, fn($v) => !empty($v));
            if (!empty($nonEmpty)) {
                $isDeans = false;
                foreach ($nonEmpty as $tv) {
                    if (in_array($tv, $vals, true)) { $isDeans = true; break; }
                }
                if (!$isDeans) {
                    abort(403, 'This application is not a Dean’s List record.');
                }
            }
        }
    }

    public function preview($applicationId)
    {
        $app = Application::where('Application_id', $applicationId)->firstOrFail();
        $this->mustBeApproved($app);

        $data = $this->buildCertificateData($app);

        $pdf = Pdf::loadView('pdf.certificates.deans_list', $data)
                  ->setPaper('a4', 'landscape');

        return $pdf->stream('deans_list_'.$applicationId.'.pdf');
    }

    public function download($applicationId)
    {
        $app = Application::where('Application_id', $applicationId)->firstOrFail();
        $this->mustBeApproved($app);

        $data = $this->buildCertificateData($app);

        $pdf = Pdf::loadView('pdf.certificates.deans_list', $data)
                  ->setPaper('a4', 'landscape');

        return $pdf->download('deans_list_'.$applicationId.'.pdf');
    }

    /**
     * NEW: Generate PNG via builder + image service, and return a /storage URL.
     */
    public function generatePng($applicationId)
    {
        $app = Application::where('Application_id', $applicationId)->firstOrFail();
        $this->mustBeApproved($app);

        $data = app(DeanCertDataBuilder::class)->buildFromStudent($app->Student_id, [
            'template_png' => public_path('img/cert/dean-template.png'),
            'out_rel'      => "cor/cert_{$app->Application_id}.png",
            'app_id'       => $app->Application_id,
            // Optional knobs:
            // 'font_scale'    => 0.95,
            // 'show_signature'=> true,
            // 'dean_name'     => 'Prof. ...',
            // 'dean_title'    => 'Dean, ...',
        ]);

        $rel = app(CertificateImageService::class)->makeDeansCertPng($data);
        if (!$rel) {
            return back()->with('error', 'Failed to generate PNG certificate.');
        }

        // Serve from public disk
        $url = asset('storage/'.$rel) . '?v=' . time(); // cache-buster
        return back()->with('png_url', $url);
    }

    // ===================== helpers =====================

    private function prettySemester($val): string
    {
        if ($val === null) return 'Semester';
        $v = is_string($val) ? strtolower(trim($val)) : $val;

        if (is_numeric($v)) {
            return ((int)$v) === 2 ? 'Second Semester' : 'First Semester';
        }

        if (is_string($v)) {
            if (preg_match('/^(2|second)$/i', $v)) return 'Second Semester';
            if (preg_match('/^(1|first)$/i',  $v)) return 'First Semester';
            return ucwords($v);
        }

        return 'Semester';
    }

    /**
     * Normalize AY like `2024-2025`, `2024 – 2025`, `2024—2025` → `2024 – 2025`
     */
    private function normalizeAy(string $ay): string
    {
        $ay = trim($ay);
        if ($ay === '') return '—';
        $ay = preg_replace('/\s*[-–—]\s*/u', ' – ', $ay);
        $ay = preg_replace('/\s+/', ' ', $ay);
        return $ay !== '' ? $ay : '—';
    }
}
