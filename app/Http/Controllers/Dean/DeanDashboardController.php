<?php

namespace App\Http\Controllers\Dean;

use App\Http\Controllers\Controller;
use App\Models\StudentCourse;
use App\Models\Application;
use App\Models\UserDesignation;

class DeanDashboardController extends Controller
{
    public function index()
    {
        $login = auth()->user();

        // Dean's campus & college (from user_designation)
        $designation = UserDesignation::where('Login_id', $login->Login_id)->first();
        $campusId    = $designation?->Campus_id;
        $collegeId   = $designation?->College_id;

        // =======================
        //  A. TOTAL STUDENTS
        // =======================
        $studentCourseBase = StudentCourse::query()
            ->when($campusId, function ($q) use ($campusId) {
                $q->where('student_course.Campus_id', $campusId);
            })
            ->when($collegeId, function ($q) use ($collegeId) {
                $q->where('student_course.College_id', $collegeId);
            });

        $totalStudents = (clone $studentCourseBase)
            ->distinct('student_course.Student_id')
            ->count('student_course.Student_id');

        // =======================
        //  B. DEAN'S LISTERS (college-scoped)
        // =======================
        // application.Type = 'DeanLister'
        $deansListerBase = Application::query()
            ->whereHas('student.studentCourse', function ($q) use ($campusId, $collegeId) {
                $q->when($campusId, function ($qq) use ($campusId) {
                    $qq->where('student_course.Campus_id', $campusId);
                })->when($collegeId, function ($qq) use ($collegeId) {
                    $qq->where('student_course.College_id', $collegeId);
                });
            })
            ->whereRaw("UPPER(Type) = 'DEANLISTER'")
            ->withStatusIn(['Approved', 'For Evaluation', 'For Approval']);

        $totalDeansListers = (clone $deansListerBase)
            ->distinct('Student_id')
            ->count('Student_id');

        // ================
        //  C. PIE DATA – DEAN'S LISTER BY MAJOR
        // ================
        $majorRaw = (clone $deansListerBase)
            ->join('student_course as sc', 'sc.Student_id', '=', 'application.Student_id')
            ->join('major as m', 'm.Major_id', '=', 'sc.Major_id')
            ->selectRaw('m.Major_name as major_name, COUNT(DISTINCT application.Student_id) as total')
            ->groupBy('m.Major_id', 'm.Major_name')
            ->orderByDesc('total')
            ->get();

        $pieMajorLabels = $majorRaw->pluck('major_name')->values();
        $pieMajorValues = $majorRaw->pluck('total')->values();

        // --- Pie chart colors (different per major) ---
        $baseColors = [
            '#660000', '#940000', '#4C0000',
            '#8B1A1A', '#A52A2A', '#B22222',
            '#7A0A0A', '#9B111E', '#C20000'
        ];

        $pieMajorColors = [];
        for ($i = 0; $i < count($pieMajorLabels); $i++) {
            $pieMajorColors[] = $baseColors[$i % count($baseColors)];
        }


        // =================================
        //  D. ENROLLED STUDENTS BY COLLEGE (only Dean's college)
        // =================================
        $collegeRaw = (clone $studentCourseBase)
            ->join('college as c', 'c.College_id', '=', 'student_course.College_id')
            ->selectRaw('c.College_name as college_name, COUNT(DISTINCT student_course.Student_id) as total_students')
            ->groupBy('student_course.College_id', 'c.College_name')
            ->orderByDesc('total_students')
            ->get();

        $collegeLabels = $collegeRaw->pluck('college_name');
        $collegeCounts = $collegeRaw->pluck('total_students');

        // ==========================
        //  E. LATIN HONORS COUNTS
        // ==========================
        $latinBase = Application::query()
            ->whereHas('student.studentCourse', function ($q) use ($campusId, $collegeId) {
                $q->when($campusId, function ($qq) use ($campusId) {
                    $qq->where('student_course.Campus_id', $campusId);
                })->when($collegeId, function ($qq) use ($collegeId) {
                    $qq->where('student_course.College_id', $collegeId);
                });
            })
            ->whereHas('post', function ($q) {
                $q->whereRaw("UPPER(Type) IN ('LATIN HONORS', 'LATIN HONOUR')");
            })
            ->withStatusIn(['APPROVED', 'QUALIFIED', 'ACCEPTED']);

        $latinRaw = (clone $latinBase)
            ->selectRaw("
                CASE 
                    WHEN UPPER(Rank) LIKE '%SUMMA%' THEN 'Summa'
                    WHEN UPPER(Rank) LIKE '%MAGNA%' THEN 'Magna'
                    WHEN UPPER(Rank) LIKE '%CUM%'   THEN 'Cum Laude'
                    ELSE 'Others'
                END as rank_group,
                COUNT(DISTINCT Student_id) as total
            ")
            ->groupBy('rank_group')
            ->get()
            ->keyBy('rank_group');

        $honorsLabels = ['Summa', 'Magna', 'Cum Laude'];
        $honorsCounts = [
            $latinRaw['Summa']->total ?? 0,
            $latinRaw['Magna']->total ?? 0,
            $latinRaw['Cum Laude']->total ?? 0,
        ];

        $totalLatinHonors = array_sum($honorsCounts);

        // ==============================
        //  F. TOP PERFORMING PROGRAMS (within Dean's college)
        // ==============================
        $topProgramsRaw = (clone $deansListerBase)
            ->join('student_course as sc', 'sc.Student_id', '=', 'application.Student_id')
            ->join('program as p', 'p.Program_id', '=', 'sc.Program_id')
            ->when($collegeId, function ($q) use ($collegeId) {
                $q->where('sc.College_id', $collegeId);
            })
            ->selectRaw('
                p.Program_id,
                p.Program_name,
                COUNT(DISTINCT application.Student_id) as total_listers,
                AVG(application.GWA) as avg_gwa
            ')
            ->groupBy('p.Program_id', 'p.Program_name')
            ->orderByDesc('total_listers')
            ->limit(6)
            ->get();

        $topPrograms = $topProgramsRaw->map(function ($row) {
            return [
                'program_name'  => $row->Program_name,
                'total_listers' => (int) $row->total_listers,
                'avg_gpa'       => $row->avg_gwa ? round($row->avg_gwa, 2) : null,
                'growth_rate'   => null,
            ];
        });

        // Placeholder for competitions
        $competitionLabels = ['IT Dept', 'CS Dept', 'BA Dept', 'Eng Dept', 'Ed Dept'];
        $competitionCounts = [0, 0, 0, 0, 0];

        return view('dean.dashboard', [
            'totalStudents'      => $totalStudents,
            'totalDeansListers'  => $totalDeansListers,
            'totalLatinHonors'   => $totalLatinHonors,

            // pie data for Dean’s Lister By Major
            'pieMajorLabels'     => $pieMajorLabels,
            'pieMajorValues'     => $pieMajorValues,
            'pieMajorColors' => $pieMajorColors,

            'collegeLabels'      => $collegeLabels,
            'collegeCounts'      => $collegeCounts,

            'honorsLabels'       => $honorsLabels,
            'honorsCounts'       => $honorsCounts,

            'topPrograms'        => $topPrograms,

            'competitionLabels'  => $competitionLabels,
            'competitionCounts'  => $competitionCounts,
        ]);
    }
}
