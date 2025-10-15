<?php

namespace App\Http\Controllers\Dean;

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
        // Inputs from query string
        $term = trim((string) $request->query('term', ''));
        $ay   = trim((string) $request->query('ay', ''));

        // Program + College for labels/filename
        $program = Program::findOrFail($programId);
        $college = College::find($program->College_id);

        // 1) Eager-load WITHOUT forcing 'Year_level' in the select
        $apps = Application::with([
                'student', // <-- let Eloquent select all student columns that exist
                'student.curriculum.curriculumAy.program' => function ($q) {
                    $q->select('Program_id','Program_name','Abbreviation');
                },
            ])
            ->where('Type', 'DeanLister')
            ->where('Status', 'Approved')
            ->whereHas('student.curriculum.curriculumAy', function ($q) use ($programId) {
                $q->where('Program_id', $programId);
            })
            ->get();

        $rows = $apps->map(function ($a) {
            $s = $a->student;

            // name
            $first  = (string)($s->First_name ?? '');
            $middle = (string)($s->Middle_name ?? '');
            $last   = (string)($s->Last_name ?? '');
            $mi     = $middle !== '' ? (mb_strtoupper(mb_substr($middle, 0, 1)).'.') : '';
            $pretty = fn($v) => mb_convert_case(trim((string)$v), MB_CASE_TITLE, 'UTF-8');
            $fullname = trim($pretty($first).' '.($mi ? $mi.' ' : '').$pretty($last));

            // gwa
            $gwaNum = is_numeric($a->GWA) ? (float)$a->GWA : INF;

            // program label
            $prog = $s->curriculum?->curriculumAy?->program;
            $programLabel = strtoupper((string)($prog->Abbreviation ?? $prog->Program_name ?? ''));

            // 2) YEAR LEVEL – try several possible columns; format nicely
            $rawYear =
                $s->Year_level
                ?? $s->year_level
                ?? $s->Year
                ?? $s->year
                ?? $a->Year_level
                ?? null;

            $year = match (true) {
                is_numeric($rawYear) && (int)$rawYear === 1 => 'FIRST YEAR',
                is_numeric($rawYear) && (int)$rawYear === 2 => 'SECOND YEAR',
                is_numeric($rawYear) && (int)$rawYear === 3 => 'THIRD YEAR',
                is_numeric($rawYear) && (int)$rawYear === 4 => 'FOURTH YEAR',
                default => ($rawYear ? strtoupper((string)$rawYear) : '—'),
            };

            return [
                '_sort_gwa' => $gwaNum,
                'no'        => 0,
                'name'      => $fullname,
                'program'   => $programLabel ?: '—',
                'year'      => $year,
                'gwa'       => is_finite($gwaNum) ? number_format($gwaNum, 4) : '—',
                'rank'      => $this->rankLabel(is_finite($gwaNum) ? $gwaNum : 10),
            ];
        })
        ->sortBy([['_sort_gwa','asc'], ['name','asc']])
        ->values()
        ->map(function ($r,$i){ $r['no']=$i+1; unset($r['_sort_gwa']); return $r; })
        ->all();


        // Header payload used by your Blade
        $header = [
            'title'       => 'DEAN’S HONORS LIST',
            'department'  => 'INFORMATION TECHNOLOGY EDUCATION PROGRAMS DEPARTMENT',
            'term'        => $term !== '' ? mb_strtoupper($term) : 'SECOND SEMESTER',
            'ay'          => $ay !== '' ? $ay : ('A.Y. '.now()->year.'-'.(now()->year + 1)),
            'university'  => 'BATANGAS STATE UNIVERSITY',
            'campus'      => '',
            'college'     => (string)($college->College_name ?? ''),
        ];

        // Render and force download (browser save dialog)
        $pdf = Pdf::loadView('reports.deans_honor_list', [
                'header'   => $header,
                'rows'     => $rows,
                // (legacy vars — safe if your Blade uses them)
                'program'  => $program,
                'college'  => $college,
                'students' => $apps,
                'term'     => $term,
                'ay'       => $ay,
            ])
            ->setPaper('A4', 'portrait');

        $programName = strtoupper($program->Program_name ?? 'PROGRAM');
        $safeProg    = Str::slug($programName, '_');
        $safeTerm    = Str::slug($header['term'], '_');
        $safeAy      = Str::slug($header['ay'], '_');
        $filename    = "Deans_Honors_List_{$safeProg}_{$safeTerm}_{$safeAy}.pdf";

        return $pdf->download($filename);
    }

    private function rankLabel(float $gwa): string
    {
        if ($gwa >= 1.0000 && $gwa <= 1.2500) return 'Tech Savant';
        if ($gwa <= 1.5000)                    return 'Tech Virtuoso';
        if ($gwa <= 1.7500)                    return 'Tech Prodigy';
        return '—';
    }
}
