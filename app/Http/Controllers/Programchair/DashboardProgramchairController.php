<?php

namespace App\Http\Controllers\Programchair;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\UserDesignation;
use App\Models\StudentCourse;
use App\Models\StudentManage;
use App\Models\GraduationForm;
use App\Models\GraduationRequirement;
use App\Models\Major;
use App\Models\Application;

class DashboardProgramchairController extends Controller
{
    public function index()
    {
        try {
            $login = auth()->user();
            if (!$login) {
                abort(403, 'Unauthorized');
            }

            // 1. Designation of current login
            $designation = UserDesignation::with(['designation', 'college', 'program', 'major'])
                ->where('Login_id', $login->Login_id)
                ->first();

            if (!$designation) {
                abort(403, 'No designation found for this user.');
            }

            $access    = $designation->designation?->Access; // 'College', 'Program', 'Major', etc.
            $collegeId = $designation->College_id;
            $programId = $designation->Program_id;
            $majorId   = $designation->Major_id;

            /**
             * 2. Base scoped students query
             *    – gagamit tayo ng STUDENT_COURSE para sa Campus/College/Program/Major
             */
            $scopedStudentsBase = StudentCourse::query()
                ->join('student_manage as sm', 'sm.Student_id', '=', 'student_course.Student_id');

            // apply scope based on Access
            if ($access === 'College' && $collegeId) {
                $scopedStudentsBase->where('student_course.College_id', $collegeId);
            } elseif ($access === 'Program' && $programId) {
                $scopedStudentsBase->where('student_course.Program_id', $programId);
            } elseif ($access === 'Major' && $majorId) {
                $scopedStudentsBase->where('student_course.Major_id', $majorId);
            }
            // else: walang extra filter → lahat ng sakop

            // ================= MAIN STATS =================

            // all students under this scope (all year levels)
            $totalStudents = (clone $scopedStudentsBase)
                ->distinct('sm.Student_id')
                ->count('sm.Student_id');

            // Dean's Listers:
            // application.Type may contain "Dean", Status = Approved
            $deansListers = (clone $scopedStudentsBase)
                ->join('application as appl', 'appl.Student_id', '=', 'sm.Student_id')
                ->whereRaw('UPPER(appl.Type) LIKE ?', ['%DEAN%'])
                ->whereRaw('UPPER(appl.Status) = ?', ['APPROVED'])
                ->distinct('sm.Student_id')
                ->count('sm.Student_id');

            // placeholders pa rin for now (kung may separate table ka later)
            $latinHonors  = 0;
            $competitions = 0;

            // ================= MAJOR LIST UNDER THIS SCOPE =================
            // Kukunin lahat ng majors na sakop ng chair (College / Program / Major)
            $majorQuery = Major::query();

            if ($collegeId) {
                $majorQuery->where('College_id', $collegeId);
            }
            if ($access === 'Program' && $programId) {
                $majorQuery->where('Program_id', $programId);
            }
            if ($access === 'Major' && $majorId) {
                $majorQuery->where('Major_id', $majorId);
            }

            $majorRows = $majorQuery
                ->select('Major_id', 'Major_name')
                ->orderBy('Major_name')
                ->get();

            $majorIds     = $majorRows->pluck('Major_id')->all();
            $gradPrograms = $majorRows->pluck('Major_name')->all(); // headers sa table

            // fallback kung sakaling walang major talaga sa scope ni chair
            if (empty($majorIds)) {
                $gradPrograms         = ['NO MAJOR FOUND'];
                $gradApplicants       = [0];
                $gradNotYet           = [0];
                $grandApplicants      = 0;
                $grandNotYet          = 0;
                $grandOverall         = 0;
                $studentsOnLOA        = 0;
                $latinHonorApplicants = 0;
                $projectedGradRate    = 0;
                $maleApplicants       = 0;
                $femaleApplicants     = 0;

                // charts dummy data
                $programLabels    = $gradPrograms;
                $programListers   = [0];
                $latinLabels      = ['Summa Cum Laude','Magna Cum Laude','Cum Laude'];
                $latinCounts      = [0, 0, 0];
                $compLabels       = ['Intra-School','Regional','National','International'];
                $compParticipants = [0, 0, 0, 0];

                return view('programchair.dashboard', compact(
                    'totalStudents',
                    'deansListers',
                    'latinHonors',
                    'competitions',
                    'gradPrograms',
                    'gradApplicants',
                    'gradNotYet',
                    'grandApplicants',
                    'grandNotYet',
                    'grandOverall',
                    'studentsOnLOA',
                    'latinHonorApplicants',
                    'projectedGradRate',
                    'maleApplicants',
                    'femaleApplicants',
                    'programLabels',
                    'programListers',
                    'latinLabels',
                    'latinCounts',
                    'compLabels',
                    'compParticipants'
                ));
            }

            // ================= GRADUATION SUMMARY PER MAJOR =================
            // init arrays keyed by Major_id para sure na lahat ng majors may column kahit 0
            $gradApplicantsByMajor = array_fill_keys($majorIds, 0);
            $gradNotYetByMajor     = array_fill_keys($majorIds, 0);

            // 4th year students only (string field: "FOURTH YEAR")
            $fourthYearBase = (clone $scopedStudentsBase)
                ->whereRaw('UPPER(sm.Year) = "FOURTH YEAR"')
                ->whereIn('student_course.Major_id', $majorIds)   // lahat ng majors under chair
                ->whereNotNull('student_course.Major_id')         // hindi isasama ang walang major
                ->leftJoin('graduation_form as gf', 'gf.Student_id', '=', 'sm.Student_id')
                ->leftJoin('graduation_requirements as gr', 'gr.GraduationForm_id', '=', 'gf.GraduationForm_id');

            $gradRows = $fourthYearBase
                ->selectRaw('
                    student_course.Major_id,
                    COUNT(*) as total_4th,
                    SUM(
                      CASE
                        WHEN gr.GraduationReq_id IS NOT NULL
                         AND UPPER(gr.remarks) = "GRADUATING"
                        THEN 1 ELSE 0
                      END
                    ) as applicants
                ')
                ->groupBy('student_course.Major_id')
                ->get();

            foreach ($gradRows as $row) {
                $mid        = $row->Major_id;
                $applicants = (int) $row->applicants;
                $total4th   = (int) $row->total_4th;

                $gradApplicantsByMajor[$mid] = $applicants;
                $gradNotYetByMajor[$mid]     = max($total4th - $applicants, 0);
            }

            // flatten to arrays in the same order as $gradPrograms / $majorIds
            $gradApplicants = [];
            $gradNotYet     = [];
            foreach ($majorIds as $mid) {
                $gradApplicants[] = $gradApplicantsByMajor[$mid] ?? 0;
                $gradNotYet[]     = $gradNotYetByMajor[$mid] ?? 0;
            }

            $grandApplicants = array_sum($gradApplicants);
            $grandNotYet     = array_sum($gradNotYet);
            $grandOverall    = $grandApplicants + $grandNotYet;

            // ================= EXTRA METRICS =================
            $studentsOnLOA        = 0;  // i-wire mo sa actual LOA data
            $latinHonorApplicants = 0;  // i-wire sa rules mo
            $projectedGradRate    = $grandOverall > 0
                ? ($grandApplicants / $grandOverall) * 100
                : 0;

            // Sex distribution among applicants (0 muna, wala pa tayong Gender column info dito)
            $maleApplicants   = 0;
            $femaleApplicants = 0;

            // sample data for other charts – pwede mong palitan
            $programLabels   = $gradPrograms;   // same labels as majors
            $programListers  = $gradApplicants; // sample: gamitin muna applicants

            $latinLabels     = ['Summa Cum Laude','Magna Cum Laude','Cum Laude'];
            $latinCounts     = [4, 25, 16];

            $compLabels       = ['Intra-School','Regional','National','International'];
            $compParticipants = [12, 8, 7, 3];

            return view('programchair.dashboard', compact(
                'totalStudents',
                'deansListers',
                'latinHonors',
                'competitions',
                'gradPrograms',
                'gradApplicants',
                'gradNotYet',
                'grandApplicants',
                'grandNotYet',
                'grandOverall',
                'studentsOnLOA',
                'latinHonorApplicants',
                'projectedGradRate',
                'maleApplicants',
                'femaleApplicants',
                'programLabels',
                'programListers',
                'latinLabels',
                'latinCounts',
                'compLabels',
                'compParticipants'
            ));
        } catch (\Throwable $e) {
            Log::error('ProgramChair dashboard error: '.$e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            abort(500, 'Dashboard failed to render. Check logs for details.');
        }
    }
}
