<?php

namespace App\Services;

use App\Models\StudentManage;

class DeanCertDataBuilder
{
    public function buildFromStudent(int $studentId, array $overrides = []): array
    {
        $student = StudentManage::query()
            ->with(['curriculum.curriculumAy.program'])
            ->findOrFail($studentId);

        // ---- Name ----
        $first  = trim((string)($student->First_name ?? ''));
        $middle = trim((string)($student->Middle_name ?? ''));
        $last   = trim((string)($student->Last_name ?? ''));
        $name   = trim($first.' '.($middle ? mb_strtoupper(mb_substr($middle,0,1)).'. ' : '').$last) ?: 'STUDENT NAME';

        // ---- Program + fallbacks ----
        $programFromChain = $student->curriculum?->curriculumAy?->program;
        $programName = $programFromChain->Program_name
            ?? $student->Program_name
            ?? $student->Course
            ?? '';

        // Degree line (avoid duplicate “Bachelor of Science in ”)
        $degreeLine = '';
        if ($programName !== '') {
            $pn = preg_replace('/^Bachelor\s+of\s+Science\s+in\s*/i', '', trim($programName));
            $degreeLine = 'Bachelor of Science in '.$pn;
        }

        // ---- Semester ----
        $rawSem = $student->Semester
            ?? $student->curriculum?->curriculumAy?->Semester
            ?? 1;
        $semester = $this->prettySemester($rawSem);

        // ---- AY ----
        $rawAy = $student->curriculum?->curriculumAy?->Academic_year
            ?? $student->School_year
            ?? '—';
        $schoolYear = $this->normalizeAy((string)$rawAy);

        // ---- GWA (optional) ----
        $gwa = $student->GWA ?? null;

        $payload = [
            'template_png'   => public_path('img/cert/dean-template.png'),
            'app_id'         => $student->Student_id, // can be overridden by caller
            'student_name'   => $name,
            'program'        => $programName,
            'degree_line'    => $degreeLine,
            'gwa'            => $gwa,
            'semester'       => $semester,
            'school_year'    => $schoolYear,
            'date_conferred' => now()->format('F d, Y'),
            'out_rel'        => "cor/cert_{$student->Student_id}.png",
        ];

        return array_replace($payload, $overrides);
    }

    private function prettySemester($val): string
    {
        $v = is_string($val) ? strtolower(trim($val)) : $val;
        if (is_numeric($v)) return ((int)$v) === 2 ? 'Second Semester' : 'First Semester';
        if (is_string($v)) {
            if (preg_match('/^2|second$/i', $v)) return 'Second Semester';
            if (preg_match('/^1|first$/i',  $v)) return 'First Semester';
            return ucwords($v);
        }
        return 'First Semester';
    }

    private function normalizeAy(string $ay): string
    {
        $ay = trim($ay);
        if ($ay === '') return '—';
        return preg_replace('/\s*[-–—]\s*/u', ' – ', preg_replace('/\s+/', ' ', $ay));
    }
}
