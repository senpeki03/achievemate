<?php

namespace App\Http\Controllers\Programchair;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Application;
use App\Models\Program;
use App\Models\College;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;

class PostingController extends Controller
{
    public function download(Request $request, int $programId)
    {
        try {
            $term = trim((string) $request->query('term', ''));
            $ay   = trim((string) $request->query('ay', ''));

            \Log::info("PostingController download called", ['programId' => $programId, 'term' => $term, 'ay' => $ay]);

            $program = Program::findOrFail($programId);
            $college = College::find($program->College_id);

            // Use student_course table to get the program relationship
            $apps = Application::with([
                'student.studentCourse.program' => function ($q) {
                    $q->select('Program_id', 'Program_name', 'Abbreviation');
                },
            ])
            ->where('Type', 'DeanLister')
            ->where('Status', 'Approved')
            ->whereHas('student.studentCourse', function ($q) use ($programId) {
                $q->where('Program_id', $programId);
            })
            ->get();

            \Log::info("PostingController - Found {$apps->count()} approved applications for program {$programId}");

            // Build rows (lowest GWA first)
            $rows = $apps->map(function ($a) {
                $s = $a->student;
                if (!$s) return null;

                // Full name
                $first  = (string)($s->First_name ?? '');
                $middle = (string)($s->Middle_name ?? '');
                $last   = (string)($s->Last_name ?? '');
                $mi     = $middle !== '' ? (mb_strtoupper(mb_substr($middle, 0, 1)).'.') : '';
                $pretty = fn($v) => mb_convert_case(trim((string)$v), MB_CASE_TITLE, 'UTF-8');
                $fullname = trim($pretty($first).' '.($mi ? $mi.' ' : '').$pretty($last));

                // GWA numeric for sorting
                $gwaNum = is_numeric($a->GWA) ? (float)$a->GWA : INF;

                // Program label: Get from student_course -> program (handle collection)
                $programLabel = '—';
                if ($s->relationLoaded('studentCourse') && $s->studentCourse) {
                    $studentCourses = $s->studentCourse;
                    
                    // studentCourse is a collection, get the first one
                    if ($studentCourses instanceof \Illuminate\Support\Collection && $studentCourses->isNotEmpty()) {
                        $firstCourse = $studentCourses->first();
                        if ($firstCourse && $firstCourse->relationLoaded('program') && $firstCourse->program) {
                            $programLabel = strtoupper((string)(
                                $firstCourse->program->Abbreviation ?? $firstCourse->program->Program_name ?? '—'
                            ));
                        }
                    }
                }

                // Year level: resolve from several possible places and normalize
                $yearLabel = $this->resolveStudentYear($s) ?? '—';

                return [
                    '_sort_gwa' => $gwaNum,
                    'no'        => 0, // will be set after sorting
                    'name'      => $fullname,
                    'program'   => $programLabel,
                    'year'      => $yearLabel,
                    'gwa'       => is_finite($gwaNum) ? number_format($gwaNum, 4) : '—',
                    'rank'      => $this->rankLabel(is_finite($gwaNum) ? $gwaNum : 10),
                ];
            })
            ->filter() // remove nulls
            ->sortBy([['_sort_gwa', 'asc'], ['name', 'asc']])
            ->values()
            ->map(function ($r, $i) { 
                $r['no'] = $i + 1; 
                unset($r['_sort_gwa']); 
                return $r; 
            })
            ->all();

            \Log::info("PostingController - Processed " . count($rows) . " rows for PDF");

            // Header for blade
            $header = [
                'title'       => 'DEAN\'S HONORS LIST',
                'department'  => 'INFORMATION TECHNOLOGY EDUCATION PROGRAMS DEPARTMENT',
                'term'        => $term !== '' ? mb_strtoupper($term) : 'SECOND SEMESTER',
                'ay'          => $ay !== '' ? $ay : ('A.Y. '.now()->year.'-'.(now()->year + 1)),
                'university'  => 'BATANGAS STATE UNIVERSITY',
                'campus'      => 'ARASOF - Nasugbu Campus',
                'college'     => (string)($college->College_name ?? 'College of Informatics and Computing Sciences'),
            ];

            $pdf = Pdf::loadView('reports.posting', [
                'header'   => $header,
                'rows'     => $rows,
                'program'  => $program,
                'college'  => $college,
                'students' => $apps,
                'term'     => $term,
                'ay'       => $ay,
            ])->setPaper('A4', 'portrait');

            $programName = strtoupper($program->Program_name ?? 'PROGRAM');
            $safeProg    = Str::slug($programName, '_');
            $safeTerm    = Str::slug($header['term'], '_');
            $safeAy      = Str::slug($header['ay'], '_');
            $filename    = "Deans_Honors_List_{$safeProg}_{$safeTerm}_{$safeAy}.pdf";

            \Log::info("PostingController - PDF generated successfully, downloading: {$filename}");

            return $pdf->download($filename);

        } catch (\Exception $e) {
            \Log::error("Error in PostingController download: " . $e->getMessage(), [
                'programId' => $programId,
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'error' => 'Failed to generate PDF: ' . $e->getMessage()
            ], 500);
        }
    }

    private function rankLabel(float $gwa): string
    {
        if ($gwa >= 1.0000 && $gwa <= 1.2500) return 'Tech Savant';
        if ($gwa <= 1.5000)                    return 'Tech Virtuoso';
        if ($gwa <= 1.7500)                    return 'Tech Prodigy';
        return '—';
    }

    /* ---------- year-level helpers ---------- */

    private function resolveStudentYear($student): ?string
    {
        // Try common columns
        $candidates = [
            $student->Year_level ?? null,
            $student->YearLevel ?? null,
            $student->Year ?? null,
            $student->year_level ?? null,
            $student->Section ?? null,
            $student->section ?? null,
        ];

        foreach ($candidates as $v) {
            $norm = $this->normalizeYearLevel($v);
            if ($norm) return $norm;
        }

        return null;
    }

    private function normalizeYearLevel($value): ?string
    {
        if ($value === null || $value === '') return null;

        // numeric → label
        if (is_numeric($value)) {
            $map = [
                1 => 'FIRST YEAR',
                2 => 'SECOND YEAR',
                3 => 'THIRD YEAR',
                4 => 'FOURTH YEAR',
            ];
            $n = (int)$value;
            return $map[$n] ?? strtoupper((string)$value);
        }

        // Try to extract a number from strings like "BSIT 3-1", "3A", etc.
        if (preg_match('/\b([1-4])\b/', (string)$value, $m)) {
            return $this->normalizeYearLevel((int)$m[1]);
        }

        // Already a text label
        return strtoupper((string)$value);
    }
}