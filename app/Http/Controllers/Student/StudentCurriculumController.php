<?php
// app/Http/Controllers/Student/StudentCurriculumController.php
namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\StudentManage;

class StudentCurriculumController extends Controller
{
    public function subjects(Request $request)
    {
        $srcode = (string) ($request->input('srcode') ?? '');
        $year   = (string) ($request->input('year_level') ?? '');
        $sem    = (string) ($request->input('semester') ?? '');
        $curriculumId = $request->input('curriculum_id');

        // 1) Resolve curriculum_id from student_manage using SRCODE (unless provided)
        if (!$curriculumId && $srcode !== '') {
            $curriculumId = StudentManage::where('SRCODE', $srcode)->value('curriculum_id');
        }

        // 2) If still none, return ok=true with empty list so your UI can proceed gracefully
        if (!$curriculumId) {
            return response()->json([
                'ok'         => true,
                'message'    => 'No curriculum found for student',
                'year_level' => $year,
                'semester'   => $sem,
                'courses'    => [],
            ], 200);
        }

        // Normalize year/sem text from QR to DB values (adjust mapping if your DB uses other labels)
        $yearDb = $this->normalizeYear($year);
        $semDb  = $this->normalizeSem($sem);

        try {
            // 3) Try common schema variants defensively
            $attempts = [
                // lower_snake
                [
                    'cs_table'   => 'curriculum_subjects',
                    'cs_cur'     => 'curriculum_id',
                    'cs_sub'     => 'subject_id',
                    'cs_year'    => 'year_level',
                    'cs_sem'     => 'semester',
                    'sub_table'  => 'subjects',
                    'sub_id'     => 'id',
                    'sub_title'  => 'title',
                ],
                // PascalCase-ish
                [
                    'cs_table'   => 'curriculum_subjects',
                    'cs_cur'     => 'Curriculum_id',
                    'cs_sub'     => 'Subject_id',
                    'cs_year'    => 'Year_level',
                    'cs_sem'     => 'Semester',
                    'sub_table'  => 'subjects',
                    'sub_id'     => 'Subject_id',
                    'sub_title'  => 'Subject_title',
                ],
                // FULL CAPS columns (some exports use this)
                [
                    'cs_table'   => 'curriculum_subjects',
                    'cs_cur'     => 'CURRICULUM_ID',
                    'cs_sub'     => 'SUBJECT_ID',
                    'cs_year'    => 'YEAR_LEVEL',
                    'cs_sem'     => 'SEMESTER',
                    'sub_table'  => 'subjects',
                    'sub_id'     => 'SUBJECT_ID',
                    'sub_title'  => 'TITLE',
                ],
            ];

            $titles = [];
            $lastErr = null;

            foreach ($attempts as $a) {
                try {
                    $q = DB::table($a['cs_table'])
                        ->join($a['sub_table'], "{$a['sub_table']}.{$a['sub_id']}", '=', "{$a['cs_table']}.{$a['cs_sub']}")
                        ->where("{$a['cs_table']}.{$a['cs_cur']}", $curriculumId);

                    if ($yearDb !== '') {
                        $q->where("{$a['cs_table']}.{$a['cs_year']}", $yearDb);
                    }
                    if ($semDb !== '') {
                        $q->where("{$a['cs_table']}.{$a['cs_sem']}", $semDb);
                    }

                    $titles = $q->pluck("{$a['sub_table']}.{$a['sub_title']}")->values()->all();

                    // If we got here with no exception, we’re done.
                    break;
                } catch (\Throwable $e) {
                    $lastErr = $e;
                    $titles = [];
                    // try next variant
                }
            }

            // Even if no titles matched (empty), still return ok so the front-end stays responsive.
            return response()->json([
                'ok'         => true,
                'year_level' => $yearDb ?: $year,
                'semester'   => $semDb  ?: $sem,
                'courses'    => $titles, // array of course titles
                // You may temporarily expose debug info if needed:
                // 'debug_error' => app()->hasDebugModeEnabled() ? ($lastErr?->getMessage()) : null,
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'ok'      => false,
                'message' => 'Lookup error',
                'error'   => app()->hasDebugModeEnabled() ? $e->getMessage() : null,
                'courses' => [],
            ], 200);
        }
    }

    private function normalizeYear(string $raw): string
    {
        $t = strtoupper(trim($raw));
        // Common QR outputs → DB values (adjust as needed)
        $map = [
            '1' => 'FIRST', 'FIRST' => 'FIRST', '1ST' => 'FIRST',
            '2' => 'SECOND', 'SECOND' => 'SECOND', '2ND' => 'SECOND',
            '3' => 'THIRD', 'THIRD' => 'THIRD', '3RD' => 'THIRD',
            '4' => 'FOURTH', 'FOURTH' => 'FOURTH', '4TH' => 'FOURTH',
        ];
        return $map[$t] ?? $t; // keep original if already matches your DB
    }

    private function normalizeSem(string $raw): string
    {
        $t = strtoupper(trim($raw));
        $map = [
            '1' => 'FIRST', 'FIRST' => 'FIRST', '1ST' => 'FIRST',
            '2' => 'SECOND', 'SECOND' => 'SECOND', '2ND' => 'SECOND',
            'SUMMER' => 'SUMMER',
            'MIDYEAR' => 'MIDYEAR',
        ];
        return $map[$t] ?? $t;
    }
}
