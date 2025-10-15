<?php

namespace App\Http\Controllers\Programchair;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Application;
use App\Models\Program;
use App\Models\College;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;

class DeansHonorListReportController extends Controller
{
    public function download(Request $request, int $programId)
    {
        $term = trim((string) $request->query('term', ''));
        $ay   = trim((string) $request->query('ay', ''));

        $program = Program::findOrFail($programId);
        $college = College::find($program->College_id);

        // Pull approved DeanLister apps for this program with program info for each student
        $apps = Application::with([
            'student.curriculum.curriculumAy.program' => function ($q) {
                // only fields that actually exist in your program table
                $q->select('Program_id', 'Program_name', 'Abbreviation');
            },
        ])
        ->where('Type', 'DeanLister')
        ->where('Status', 'Approved')
        ->whereHas('student.curriculum.curriculumAy', function ($q) use ($programId) {
            $q->where('Program_id', $programId);
        })
        ->get();

        // Build rows (lowest GWA first)
        $rows = $apps->map(function ($a) {
            $s = $a->student;

            // Full name
            $first  = (string)($s->First_name ?? '');
            $middle = (string)($s->Middle_name ?? '');
            $last   = (string)($s->Last_name ?? '');
            $mi     = $middle !== '' ? (mb_strtoupper(mb_substr($middle, 0, 1)).'.') : '';
            $pretty = fn($v) => mb_convert_case(trim((string)$v), MB_CASE_TITLE, 'UTF-8');
            $fullname = trim($pretty($first).' '.($mi ? $mi.' ' : '').$pretty($last));

            // GWA numeric for sorting
            $gwaNum = is_numeric($a->GWA) ? (float)$a->GWA : INF;

            // Program label: Abbreviation -> Program_name
            $prog = $s->curriculum?->curriculumAy?->program;
            $programLabel = strtoupper((string)(
                $prog->Abbreviation
                ?? $prog->Program_name
                ?? ''
            ));

            // Year level: resolve from several possible places and normalize
            $yearLabel = $this->resolveStudentYear($s) ?? '—';

            return [
                '_sort_gwa' => $gwaNum,
                'no'        => 0, // will be set after sorting
                'name'      => $fullname,
                'program'   => $programLabel ?: '—',
                'year'      => $yearLabel,
                'gwa'       => is_finite($gwaNum) ? number_format($gwaNum, 4) : '—',
                'rank'      => $this->rankLabel(is_finite($gwaNum) ? $gwaNum : 10),
            ];
        })
        ->sortBy([['_sort_gwa', 'asc'], ['name', 'asc']])
        ->values()
        ->map(function ($r, $i) { $r['no'] = $i + 1; unset($r['_sort_gwa']); return $r; })
        ->all();

        // Header for blade
        $header = [
            'title'       => 'DEAN’S HONORS LIST',
            'department'  => 'INFORMATION TECHNOLOGY EDUCATION PROGRAMS DEPARTMENT',
            'term'        => $term !== '' ? mb_strtoupper($term) : 'SECOND SEMESTER',
            'ay'          => $ay !== '' ? $ay : ('A.Y. '.now()->year.'-'.(now()->year + 1)),
            'university'  => 'BATANGAS STATE UNIVERSITY',
            'campus'      => '',
            'college'     => (string)($college->College_name ?? ''),
        ];

        $pdf = Pdf::loadView('reports.deans_honor_list', [
            'header'   => $header,
            'rows'     => $rows,
            // legacy vars if the blade still references them
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

        // Force a file download (Save dialog)
        return $pdf->download($filename);
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
            $student->Section ?? null,   // sometimes embedded like "3A", "BSIT 3-1"
            $student->section ?? null,
        ];

        foreach ($candidates as $v) {
            $norm = $this->normalizeYearLevel($v);
            if ($norm) return $norm;
        }

        // If you store it inside related models, add those lookups here as needed
        // $sec = $student->curriculum->curriculumAy->section->name ?? null;

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
