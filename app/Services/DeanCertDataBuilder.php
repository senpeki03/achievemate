<?php

namespace App\Services;

use App\Models\StudentManage;
use App\Models\StudentCourse;
use App\Models\Post;

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
        $name   = trim(
            $first . ' ' .
            ($middle ? mb_strtoupper(mb_substr($middle, 0, 1)) . '. ' : '') .
            $last
        ) ?: 'STUDENT NAME';

        // ============================================================
        //  Program (from student_course.program first, then fallbacks)
        // ============================================================
        $programName = '';
        $programId   = null;

        try {
            $sc = StudentCourse::with('program')
                ->where('Student_id', $studentId)
                ->latest('StudentCourse_id')
                ->first();

            if ($sc) {
                $programId = $sc->Program_id;
                if ($sc->program) {
                    $programName = $sc->program->Program_name;
                }
            }
        } catch (\Throwable $e) {
            // optional: log if you want
            // \Log::warning('DeanCertDataBuilder: student_course lookup failed', ['err' => $e->getMessage()]);
        }

        // fallback kung wala talagang nakuha
        if ($programName === '') {
            $programFromChain = $student->curriculum?->curriculumAy?->program;

            $programName = $programFromChain->Program_name
                ?? $student->Program_name
                ?? $student->Course
                ?? '';
        }

        // Degree line (avoid duplicate “Bachelor of Science in ”)
        $degreeLine = '';
        if ($programName !== '') {
            $pn = preg_replace('/^Bachelor\s+of\s+Science\s+in\s*/i', '', trim($programName));
            $degreeLine = 'Bachelor of Science in ' . $pn;
        }

        // ===========================================
        //  Semester & AY (priority: Post → fallback)
        // ===========================================

        // default (old behavior – from curriculum / student)
        $rawSem = $student->Semester
            ?? $student->curriculum?->curriculumAy?->Semester
            ?? 1;
        $semester   = $this->prettySemester($rawSem);

        $rawAy = $student->curriculum?->curriculumAy?->Academic_year
            ?? $student->School_year
            ?? '—';
        $schoolYear = $this->normalizeAy((string)$rawAy);

        // Try override using active Post for this program
        try {
            if ($programId) {
                $now = now()->toDateString();

                $post = Post::query()
                    ->whereHas('userDesignation', function ($q) use ($programId) {
                        $q->where('Program_id', $programId);
                    })
                    ->whereDate('Start_date', '<=', $now)
                    ->whereDate('End_date', '>=', $now)
                    ->latest('Post_id')
                    ->first();

                if ($post) {
                    if (!empty($post->Semester)) {
                        $semester = $this->prettySemester($post->Semester);
                    }
                    if (!empty($post->Academic_year)) {
                        $schoolYear = $this->normalizeAy($post->Academic_year);
                    }
                }
            }
        } catch (\Throwable $e) {
            // optional: log if you want
            // \Log::warning('DeanCertDataBuilder: Post lookup failed', ['err' => $e->getMessage()]);
        }

        // ---- GWA (optional) ----
        $gwa = $student->GWA ?? null;

        $payload = [
            'template_png'   => public_path('img/cert/dean-template.png'),
            'app_id'         => $student->Student_id, // usually overridden by caller
            'student_name'   => $name,
            'program'        => $programName,
            'degree_line'    => $degreeLine,
            'gwa'            => $gwa,
            'semester'       => $semester,
            'school_year'    => $schoolYear,
            'date_conferred' => now()->format('F d, Y'),
            'out_rel'        => "cor/cert_{$student->Student_id}.png", // usually overridden
        ];

        return array_replace($payload, $overrides);
    }

    private function prettySemester($val): string
    {
        $v = is_string($val) ? strtolower(trim($val)) : $val;

        if (is_numeric($v)) {
            return ((int) $v) === 2 ? 'Second Semester' : 'First Semester';
        }

        if (is_string($v)) {
            if (preg_match('/^(2|second)$/i', $v)) return 'Second Semester';
            if (preg_match('/^(1|first)$/i', $v))  return 'First Semester';
            return ucwords($v);
        }

        return 'First Semester';
    }

    private function normalizeAy(string $ay): string
    {
        $ay = trim($ay);
        if ($ay === '') return '—';

        // normalize spaces and dash
        $ay = preg_replace('/\s+/', ' ', $ay);
        $ay = preg_replace('/\s*[-–—]\s*/u', ' – ', $ay);

        return $ay;
    }
}
