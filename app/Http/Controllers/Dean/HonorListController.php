<?php

namespace App\Http\Controllers\Dean;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use App\Models\College;
use App\Models\Program;
use App\Models\Application;
use App\Models\UserDesignation;
use App\Models\Evaluation;

class HonorListController extends Controller
{
    public function index($campusId = null)
    {
        $authUser = auth()->user();
        if (!$authUser) {
            return redirect()->route('login')->with('error', 'Please login to access this page.');
        }

        \Log::info('Dean accessing honor list', [
            'user_id'   => $authUser->Login_id,
            'user_name' => $authUser->name,
            'campus_id' => $campusId
        ]);

        // Get the Dean's designation to determine which colleges they can access
        $userDesignation = UserDesignation::where('Login_id', $authUser->Login_id)->first();

        if ($userDesignation) {
            \Log::info('Dean designation found:', [
                'designation_id' => $userDesignation->Designation_id,
                'campus_id'      => $userDesignation->Campus_id,
                'college_id'     => $userDesignation->College_id,
                'program_id'     => $userDesignation->Program_id
            ]);

            // If Dean has a specific college designation, show only that college
            if ($userDesignation->College_id) {
                $colleges = College::where('College_id', $userDesignation->College_id)->get();
                \Log::info('Filtered colleges by dean designation', [
                    'college_id' => $userDesignation->College_id,
                    'count'      => $colleges->count()
                ]);
            }
            // If Dean has campus designation but no specific college, show all colleges in that campus
            elseif ($userDesignation->Campus_id) {
                $colleges = College::where('Campus_id', $userDesignation->Campus_id)->get();
                \Log::info('Filtered colleges by campus designation', [
                    'campus_id' => $userDesignation->Campus_id,
                    'count'     => $colleges->count()
                ]);
            }
            // If no specific designation, show all colleges
            else {
                $colleges = College::all();
                \Log::info('Loaded all colleges (no specific designation)', [
                    'count' => $colleges->count()
                ]);
            }
        } else {
            // No designation found - this might be the issue!
            \Log::warning('No user designation found for dean', [
                'login_id' => $authUser->Login_id
            ]);

            // Show all colleges as fallback
            $colleges = College::all();
            \Log::info('No designation - loaded all colleges as fallback', [
                'count' => $colleges->count()
            ]);
        }

        // Override with campus filter if provided in URL
        if ($campusId && $campusId !== 'null') {
            $colleges = College::where('Campus_id', $campusId)->get();
            \Log::info('Overridden with URL campus filter', [
                'campus_id' => $campusId,
                'count'     => $colleges->count()
            ]);
        }

        // Debug: Log college details
        if ($colleges->isNotEmpty()) {
            \Log::info('Colleges to display:', $colleges->pluck('College_name', 'College_id')->toArray());
        } else {
            \Log::warning('No colleges found after all filters');
        }

        return view('dean.honorlist', [
            'colleges'        => $colleges,
            'programs'        => collect([]),
            'students'        => collect([]),
            'userDesignation' => $userDesignation // Pass for debugging
        ]);
    }

    public function getProgramsByCollege($collegeId)
    {
        \Log::info('Fetching programs for college:', ['college_id' => $collegeId]);

        // Get programs for the selected college
        $programs = Program::where('College_id', $collegeId)->get();

        \Log::info('Programs found:', ['count' => $programs->count()]);

        if ($programs->isEmpty()) {
            return response()->json(['message' => 'No programs found for this college.'], 404);
        }

        return response()->json(
            $programs->map(function ($program) {
                return [
                    'Program_id'   => $program->Program_id,
                    'Program_name' => $program->Program_name,
                ];
            }),
            200,
            [],
            JSON_UNESCAPED_UNICODE
        );
    }

    /**
     * Return applications with Status = 'For Approval' or 'Approved'
     * + latest evaluation Date per student from evaluation table.
     */
    public function getStudentsByProgram($programId)
    {
        \Log::info('Fetching students for program:', ['program_id' => $programId]);

        try {
            // 1) Applications for this program (For Approval / Approved)
            $apps = Application::with(['student' => function ($query) {
                    $query->select('Student_id', 'First_name', 'Middle_name', 'Last_name', 'Year');
                }])
                ->whereHas('student.studentCourse', function ($q) use ($programId) {
                    $q->where('Program_id', $programId);
                })
                ->whereIn('Status', ['For Approval', 'Approved'])
                ->orderBy('GWA')
                ->orderBy('Application_id')
                ->get();

            \Log::info('Applications found:', ['count' => $apps->count()]);

            // 2) Collect student IDs from these applications
            $studentIds = $apps->pluck('Student_id')->unique()->values()->all();

            // 3) Latest evaluation Date per student (single query)
            $evaluationDates = collect();

            if (!empty($studentIds)) {
                $evaluations = Evaluation::whereIn('Student_id', $studentIds)
                    ->select(
                        'Student_id',
                        DB::raw('MAX(`Date`) as eval_date')
                    )
                    ->groupBy('Student_id')
                    ->get();

                $evaluationDates = $evaluations->pluck('eval_date', 'Student_id');

                foreach ($evaluations as $row) {
                    \Log::info('Eval map', [
                        'student_id' => $row->Student_id,
                        'eval_date'  => $row->eval_date,
                    ]);
                }
            }

            // 4) Map to JSON payload
            $students = $apps->map(function ($app) use ($evaluationDates) {
                $s = $app->student;

                $full = trim(
                    ($s->First_name ?? '') . ' ' .
                    ($s->Middle_name ?? '') . ' ' .
                    ($s->Last_name ?? '')
                );

                // Format GWA to 4 decimal places
                $gwaFormatted = '—';
                if ($app->GWA !== null && is_numeric($app->GWA)) {
                    $gwaFormatted = number_format((float)$app->GWA, 4, '.', '');
                }

                $studentId = $app->Student_id;
                $evalDate  = $evaluationDates->get($studentId); // "YYYY-MM-DD" or null

                \Log::info('DeanList row', [
                    'student_id' => $studentId,
                    'fullname'   => $full,
                    'status'     => $app->Status,
                    'eval_date'  => $evalDate ?? 'NULL',
                ]);

                return [
                    'application_id'  => $app->Application_id,
                    'student_id'      => $studentId,
                    'fullname'        => $full ?: 'Unknown Student',
                    'year_level'      => $s->Year ?? '',
                    'status'          => $app->Status ?? 'Pending',
                    'gwa'             => $app->GWA,          // raw for sorting
                    'gwa_formatted'   => $gwaFormatted,      // formatted for display
                    'rank'            => $app->Rank ?? '',

                    // --- evaluation date keys (para tugma sa JS) ---
                    'Date'            => $evalDate,
                    'evaluation_date' => $evalDate,
                    'date'            => $evalDate,
                ];
            })->values();

            return response()->json($students, 200, [], JSON_UNESCAPED_UNICODE);

        } catch (\Exception $e) {
            \Log::error('Error fetching students:', [
                'program_id' => $programId,
                'error'      => $e->getMessage()
            ]);

            return response()->json(
                ['message' => 'Error loading students: ' . $e->getMessage()],
                500
            );
        }
    }

    public function viewFile($id)
    {
        $application = Application::findOrFail($id);

        if (!$application->File_data) {
            abort(404, 'File not found');
        }

        return response($application->File_data, 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="'.$application->File_name.'"');
    }
}
