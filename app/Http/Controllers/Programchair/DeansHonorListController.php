<?php

namespace App\Http\Controllers\Programchair;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\College;
use App\Models\Program;
use App\Models\UserDesignation;
use App\Models\Application;
use App\Models\CurriculumAy;
use App\Models\Evaluation;
use Illuminate\Support\Facades\Log;

class DeansHonorListController extends Controller
{
    public function index()
    {
        $authUser = auth()->user();
        if (!$authUser) {
            return redirect()->route('login')->with('error', 'Please login to access this page.');
        }

        $userDesignation = UserDesignation::where('Login_id', $authUser->Login_id)->first();

        if (!$userDesignation) {
            return view('programchair.deanshonorlist', [
                'colleges' => [],
                'programs' => [],
                'students' => [],
            ]);
        }

        // Method 1: Get colleges using the correct relationship name
        $colleges = College::whereHas('curriculumAy', function($query) use ($userDesignation) {
            $query->where('Campus_id', $userDesignation->Campus_id);
        })->get();

        // Method 2: Alternative approach using CurriculumAy directly
        if ($colleges->isEmpty()) {
            $colleges = CurriculumAy::with('college')
                ->where('Campus_id', $userDesignation->Campus_id)
                ->get()
                ->pluck('college')
                ->filter()
                ->unique('College_id')
                ->values();
        }

        // Method 3: Final fallback - get all colleges from the campus
        if ($colleges->isEmpty()) {
            $colleges = College::where('Campus_id', $userDesignation->Campus_id)->get();
        }

        return view('programchair.deanshonorlist', [
            'colleges' => $colleges,
            'programs' => [],
            'students' => [],
        ]);
    }

    public function getProgramsByCollege($collegeId)
    {
        $authUser = auth()->user();
        $userDesignation = UserDesignation::where('Login_id', $authUser->Login_id)->first();

        if (!$userDesignation) {
            return response()->json([]);
        }

        // Get programs from CurriculumAy that match the college and campus
        $programs = CurriculumAy::with('program')
            ->where('Campus_id', $userDesignation->Campus_id)
            ->where('College_id', $collegeId)
            ->get()
            ->pluck('program')
            ->filter()
            ->unique('Program_id')
            ->values()
            ->map(function($program) {
                return [
                    'Program_id'   => $program->Program_id,
                    'Program_name' => $program->Program_name,
                ];
            });

        // Fallback: Direct program query
        if ($programs->isEmpty()) {
            $programs = Program::where('College_id', $collegeId)
                ->get()
                ->map(function($program) {
                    return [
                        'Program_id'   => $program->Program_id,
                        'Program_name' => $program->Program_name,
                    ];
                });
        }

        return response()->json($programs);
    }

    public function getStudentsByProgram($programId)
    {
        try {
            Log::info("=== Getting students for program ID: " . $programId . " ===");

            // SIMPLE APPROACH: Get all applications and manually filter by program
            $allApplications = Application::with(['student'])->get();
            
            Log::info("Total applications in system: " . $allApplications->count());

            // Filter applications where student belongs to the selected program
            $filteredApps = $allApplications->filter(function($app) use ($programId) {
                if (!$app->student) {
                    return false;
                }
                
                // Check if student has this program in StudentCourse
                if ($app->student->studentCourse) {
                    foreach ($app->student->studentCourse as $course) {
                        if ($course->Program_id == $programId) {
                            return true;
                        }
                    }
                }
                
                return false;
            });

            Log::info("Applications filtered for program {$programId}: " . $filteredApps->count());

            // Get evaluation dates for these applications
            $evaluationDates = [];
            $studentIds = $filteredApps->pluck('student.Student_id')->filter()->toArray();
            
            if (!empty($studentIds)) {
                $evaluations = Evaluation::whereIn('Student_id', $studentIds)->get();
                foreach ($evaluations as $eval) {
                    $evaluationDates[$eval->Student_id] = $eval->Date;
                }
            }

            // Sort by GWA
            $sorted = $filteredApps->sortBy(function ($app) {
                $gwaNum = is_numeric($app->GWA) ? (float)$app->GWA : INF;
                $ln = strtoupper(trim($app->student->Last_name ?? ''));
                $fn = strtoupper(trim($app->student->First_name ?? ''));
                return [$gwaNum, $ln, $fn];
            })->values();

            $students = $sorted->map(function ($app) use ($evaluationDates) {
                $s = $app->student;
                $fullname = trim(($s->First_name ?? '') . ' ' . ($s->Middle_name ?? '') . ' ' . ($s->Last_name ?? ''));
                
                // Get evaluation date if exists
                $evalDate = isset($evaluationDates[$s->Student_id]) ? $evaluationDates[$s->Student_id] : null;
                
                return [
                    'application_id' => $app->Application_id,
                    'student_id'     => $s->Student_id, // Add student_id for evaluation lookup
                    'fullname'       => $fullname,
                    'year_level'     => $s->Year ?? 'N/A',
                    'status'         => $app->Status ?? 'Pending',
                    'gwa'            => $app->GWA,
                    'rank'           => $app->Rank,
                    'evaluation_date' => $evalDate, // Add evaluation date
                ];
            });

            Log::info("Returning " . $students->count() . " students");
            
            return response()->json($students);

        } catch (\Exception $e) {
            Log::error("Error in getStudentsByProgram: " . $e->getMessage());
            Log::error("Stack trace: " . $e->getTraceAsString());
            
            return response()->json([
                'error' => 'Failed to load students',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function viewFile($id)
    {
        $application = Application::findOrFail($id);

        return response($application->File_data, 200)
            ->header('Content-Type', 'application/pdf')
            ->header(
                'Content-Disposition',
                'inline; filename="'.($application->File_name ?? "application-$id.pdf").'"'
            );
    }

    public function updateStatus(Request $request)
    {
        try {
            Log::info("=== updateStatus called ===");
            Log::info("Request data: ", $request->all());

            $request->validate([
                'id'     => 'required|integer|exists:application,Application_id',
                'status' => 'required|in:For Approval',
                'evaluation_date' => 'sometimes|date'
            ]);

            // Get the authenticated user's Login_id
            $authUser = auth()->user();
            if (!$authUser) {
                Log::error("No authenticated user found");
                return response()->json(['message' => 'User not authenticated'], 401);
            }

            // Get user designation to get the correct User_id
            $userDesignation = UserDesignation::where('Login_id', $authUser->Login_id)->first();
            if (!$userDesignation) {
                Log::error("User designation not found for Login_id: " . $authUser->Login_id);
                return response()->json(['message' => 'User designation not found'], 404);
            }

            $application = Application::with('student')->find($request->id);
            if (!$application) {
                Log::error("Application not found with ID: " . $request->id);
                return response()->json(['message' => 'Application not found'], 404);
            }

            if (!$application->student) {
                Log::error("Student not found for application ID: " . $request->id);
                return response()->json(['message' => 'Student not found for this application'], 404);
            }

            // Update application status
            $application->Status = 'For Approval';
            $application->save();

            // Create or update evaluation record
            $evaluationDate = $request->evaluation_date ?? now()->toDateString();
            
            Log::info("Creating evaluation record for Student ID: " . $application->student->Student_id);
            Log::info("Evaluation date: " . $evaluationDate);
            Log::info("User ID from designation: " . $userDesignation->User_id);
            Log::info("Login ID: " . $authUser->Login_id);

            Evaluation::updateOrCreate(
                [
                    'Student_id' => $application->student->Student_id,
                ],
                [
                    'User_id' => $userDesignation->User_id, // Use User_id from user_designation
                    'Date' => $evaluationDate,
                    'Academic_year' => $this->getCurrentAcademicYear()
                ]
            );

            Log::info("Evaluation record created/updated successfully");

            return response()->json([
                'ok' => true, 
                'message' => 'Status updated and evaluation recorded successfully',
                'evaluation_date' => $evaluationDate
            ]);

        } catch (\Exception $e) {
            Log::error("Error in updateStatus: " . $e->getMessage());
            Log::error("Stack trace: " . $e->getTraceAsString());
            
            return response()->json([
                'error' => 'Failed to update status',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function bulkVerify(Request $request)
    {
        try {
            Log::info("=== bulkVerify called ===");
            Log::info("Request data: ", $request->all());

            $data = $request->validate([
                'application_ids'   => 'required|array|min:1',
                'application_ids.*' => 'integer|exists:application,Application_id',
                'evaluation_date' => 'sometimes|date'
            ]);

            // Get the authenticated user's Login_id
            $authUser = auth()->user();
            if (!$authUser) {
                Log::error("No authenticated user found");
                return response()->json(['message' => 'User not authenticated'], 401);
            }

            // Get user designation to get the correct User_id
            $userDesignation = UserDesignation::where('Login_id', $authUser->Login_id)->first();
            if (!$userDesignation) {
                Log::error("User designation not found for Login_id: " . $authUser->Login_id);
                return response()->json(['message' => 'User designation not found'], 404);
            }

            // Get all applications with students
            $applications = Application::with('student')->whereIn('Application_id', $data['application_ids'])->get();

            if ($applications->isEmpty()) {
                Log::error("No applications found with the provided IDs");
                return response()->json(['message' => 'No applications found'], 404);
            }

            // Update application statuses
            Application::whereIn('Application_id', $data['application_ids'])
                ->update(['Status' => 'For Approval']);

            // Create evaluation records for each application
            $evaluationDate = $request->evaluation_date ?? now()->toDateString();
            $userId = $userDesignation->User_id; // Use User_id from user_designation
            $academicYear = $this->getCurrentAcademicYear();

            $evaluationCount = 0;
            foreach ($applications as $application) {
                if ($application->student) {
                    Evaluation::updateOrCreate(
                        [
                            'Student_id' => $application->student->Student_id,
                        ],
                        [
                            'User_id' => $userId,
                            'Date' => $evaluationDate,
                            'Academic_year' => $academicYear
                        ]
                    );
                    $evaluationCount++;
                } else {
                    Log::warning("No student found for application ID: " . $application->Application_id);
                }
            }

            Log::info("Created/updated {$evaluationCount} evaluation records");

            return response()->json([
                'ok' => true, 
                'message' => 'Selected applications verified and evaluations recorded.',
                'evaluation_date' => $evaluationDate,
                'evaluations_created' => $evaluationCount
            ]);

        } catch (\Exception $e) {
            Log::error("Error in bulkVerify: " . $e->getMessage());
            Log::error("Stack trace: " . $e->getTraceAsString());
            
            return response()->json([
                'error' => 'Failed to verify selected applications',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function downloadReport($programId, Request $request)
    {
        $term = $request->get('term', '');
        $academicYear = $request->get('ay', '');

        // Get approved students for this program
        $students = Application::with(['student.curriculum.curriculumAy.program'])
            ->whereHas('student.curriculum.curriculumAy', function ($q) use ($programId) {
                $q->where('Program_id', $programId);
            })
            ->where('Status', 'Approved')
            ->get()
            ->sortBy(function ($app) {
                $gwaNum = is_numeric($app->GWA) ? (float)$app->GWA : INF;
                $ln = strtoupper(trim($app->student->Last_name ?? ''));
                $fn = strtoupper(trim($app->student->First_name ?? ''));
                return [$gwaNum, $ln, $fn];
            })
            ->values();

        // Here you would generate and return the PDF report
        // For now, return a JSON response or implement your PDF generation logic
        return response()->json([
            'program_id' => $programId,
            'term' => $term,
            'academic_year' => $academicYear,
            'students_count' => $students->count(),
            'students' => $students->map(function($app) {
                return [
                    'name' => $app->student->full_name ?? 'N/A',
                    'gwa' => $app->GWA,
                    'year_level' => $app->student->Year ?? 'N/A',
                ];
            })
        ]);
    }

    /**
     * Get the current academic year
     */
    private function getCurrentAcademicYear()
    {
        $currentYear = date('Y');
        $currentMonth = date('n');
        
        if ($currentMonth < 6) {
            return ($currentYear - 1) . '-' . $currentYear;
        } else {
            return $currentYear . '-' . ($currentYear + 1);
        }
    }
}