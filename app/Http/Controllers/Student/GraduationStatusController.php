<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\GraduationForm;
use App\Models\GraduationRequirement;
use App\Models\StudentManage;
use App\Models\StudentCourse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class GraduationStatusController extends Controller
{
    /**
     * Display the graduation status page for a student
     */
    public function show(Request $request)
    {
        $loginId = optional($request->user())->Login_id
            ?? session('login_id')
            ?? session('Login_id');

        $student = $loginId ? StudentManage::where('Login_id', $loginId)->first() : null;

        if (!$student) {
            return redirect()->route('student.dashboard')->with('error', 'Student not found.');
        }

        // STEP 1: Check if Student_id exists in graduation_form table (application submitted)
        $graduationForm = GraduationForm::where('Student_id', $student->Student_id)->first();

        if (!$graduationForm) {
            return redirect()->route('student.graduation.show')
                ->with('error', 'Please complete your graduation application first.');
        }

        // STEP 2: Check if GraduationForm_id exists in graduation_requirements table (requirements completed)
        $graduationRequirements = GraduationRequirement::where('GraduationForm_id', $graduationForm->GraduationForm_id)->first();

        // Get student's program information
        $programInfo = $this->getStudentProgramInfo($student);

        // Determine graduation status based on the two-step detection
        $graduationStatus = $this->determineGraduationStatus($graduationForm, $graduationRequirements);

        // Get requirements checklist
        $requirementsChecklist = $this->getRequirementsChecklist($graduationRequirements);

        // ✅ NEW: Get remarks from graduation_requirements
        $remarks = $graduationRequirements ? $graduationRequirements->remarks : null;

        return view('student.graduationstatus', compact(
            'student',
            'graduationForm',
            'graduationRequirements',
            'programInfo',
            'graduationStatus',
            'requirementsChecklist',
            'remarks' // ✅ Include remarks in the view data
        ));
    }

    /**
     * Get student's program information
     */
    private function getStudentProgramInfo($student)
    {
        $programInfo = [
            'college' => '',
            'program' => '',
            'major' => '',
            'year_level' => ''
        ];

        // Get from student_course table
        $course = StudentCourse::with(['college', 'program', 'major'])
            ->where('Student_id', $student->Student_id)
            ->orderByDesc('StudentCourse_id')
            ->first();

        if ($course) {
            if ($course->college) {
                $programInfo['college'] = $course->college->College_name ?? '';
            }
            if ($course->program) {
                $programInfo['program'] = $course->program->Program_name ?? '';
            }
            if ($course->major) {
                $programInfo['major'] = $course->major->Major_name ?? '';
            }
        }

        // Fallback to curriculum data if needed
        if (empty($programInfo['college']) || empty($programInfo['program']) || empty($programInfo['major'])) {
            try {
                $curriculumId = $student->curriculum_id ?? $student->Curriculum_id ?? null;
                if ($curriculumId) {
                    $curr = DB::table('curriculum')->where('curriculum_id', $curriculumId)->first();
                    $currAyId = $curr->CurriculumAY_id ?? ($curr->CurriculumAMY_id ?? null);

                    if ($currAyId) {
                        $cay = DB::table('curriculum_ay')->where('CurriculumAY_id', $currAyId)->first();
                        if ($cay) {
                            if (empty($programInfo['college']) && $cay->College_id) {
                                $college = DB::table('college')->where('College_id', $cay->College_id)->first();
                                if ($college) {
                                    $programInfo['college'] = $college->College_name ?? $college->Name ?? '';
                                }
                            }
                            if (empty($programInfo['program']) && $cay->Program_id) {
                                $program = DB::table('program')->where('Program_id', $cay->Program_id)->first();
                                if ($program) {
                                    $programInfo['program'] = $program->Program_name ?? $program->Name ?? '';
                                }
                            }
                            if (empty($programInfo['major']) && $cay->Major_id) {
                                $major = DB::table('major')->where('Major_id', $cay->Major_id)->first();
                                if ($major) {
                                    $programInfo['major'] = $major->Major_name ?? $major->Name ?? '';
                                }
                            }
                        }
                    }
                }
            } catch (\Throwable $e) {
                // Silent fallback
            }
        }

        return $programInfo;
    }

    /**
     * Determine the graduation status based on two-step detection
     */
    private function determineGraduationStatus($graduationForm, $graduationRequirements)
    {
        $status = [
            'text' => 'NOT APPLIED',
            'badge_class' => 'bg-secondary',
            'description' => 'You have not applied for graduation yet.',
            'is_graduating' => false
        ];

        // STEP 1: Check if Student_id exists in graduation_form table
        if (!$graduationForm) {
            return $status;
        }

        // STEP 2: Check if GraduationForm_id exists in graduation_requirements table
        if ($graduationRequirements) {
            // ✅ NEW: Check remarks for specific status
            $remarks = strtolower(trim($graduationRequirements->remarks ?? ''));
            
            if (str_contains($remarks, 'approved') || str_contains($remarks, 'cleared')) {
                $status['text'] = 'APPROVED FOR GRADUATION';
                $status['badge_class'] = 'bg-success';
                $status['description'] = 'Congratulations! Your graduation has been approved.';
                $status['is_graduating'] = true;
            } elseif (str_contains($remarks, 'pending') || str_contains($remarks, 'review')) {
                $status['text'] = 'UNDER REVIEW';
                $status['badge_class'] = 'bg-warning';
                $status['description'] = 'Your graduation application is under review.';
                $status['is_graduating'] = false;
            } elseif (str_contains($remarks, 'rejected') || str_contains($remarks, 'denied')) {
                $status['text'] = 'APPLICATION REJECTED';
                $status['badge_class'] = 'bg-danger';
                $status['description'] = 'Your graduation application requires attention. Please check remarks.';
                $status['is_graduating'] = false;
            } else {
                $status['text'] = 'GRADUATING';
                $status['badge_class'] = 'bg-info';
                $status['description'] = 'Your graduation application has been submitted and requirements completed.';
                $status['is_graduating'] = true;
            }
        } else {
            $status['text'] = 'APPLICATION SUBMITTED';
            $status['badge_class'] = 'bg-primary';
            $status['description'] = 'Your graduation application has been submitted. Please complete the requirements by uploading your documents.';
        }

        return $status;
    }

    /**
     * Get detailed requirements checklist using actual requirement columns
     */
    private function getRequirementsChecklist($graduationRequirements)
    {
        $hasReq = (bool) $graduationRequirements;

        $hasApproval   = $hasReq && !empty($graduationRequirements->Approval_Sheet);
        $hasLibrary    = $hasReq && !empty($graduationRequirements->Certificate_Library);
        $hasBarangay   = $hasReq && !empty($graduationRequirements->Barangay_Clearance);
        $hasBirthCert  = $hasReq && !empty($graduationRequirements->Birth_Certificate);
        $hasGradForm   = $hasReq && !empty($graduationRequirements->applicationform_grad);
        $hasROG        = $hasReq && !empty($graduationRequirements->reportofgrade_path);

        return [
            [
                'name'        => 'Approval Sheet',
                'completed'   => $hasApproval,
                'status_text' => $hasApproval ? 'Submitted' : 'Pending',
                'badge_class' => $hasApproval ? 'bg-success' : 'bg-warning',
                'description' => $hasApproval ? 'Approval Sheet uploaded' : 'Approval Sheet required',
            ],
            [
                'name'        => 'Library Clearance',
                'completed'   => $hasLibrary,
                'status_text' => $hasLibrary ? 'Cleared' : 'Pending',
                'badge_class' => $hasLibrary ? 'bg-success' : 'bg-warning',
                'description' => $hasLibrary ? 'Library Clearance uploaded' : 'Library Clearance required',
            ],
            [
                'name'        => 'Barangay Clearance',
                'completed'   => $hasBarangay,
                'status_text' => $hasBarangay ? 'Submitted' : 'Pending',
                'badge_class' => $hasBarangay ? 'bg-success' : 'bg-warning',
                'description' => $hasBarangay ? 'Barangay Clearance uploaded' : 'Barangay Clearance required',
            ],
            [
                'name'        => 'Birth Certificate (PSA)',
                'completed'   => $hasBirthCert,
                'status_text' => $hasBirthCert ? 'Submitted' : 'Pending',
                'badge_class' => $hasBirthCert ? 'bg-success' : 'bg-warning',
                'description' => $hasBirthCert ? 'Birth Certificate uploaded' : 'Birth Certificate required',
            ],
            [
                'name'        => 'Graduation Application Form',
                'completed'   => $hasGradForm,
                'status_text' => $hasGradForm ? 'Submitted' : 'Pending',
                'badge_class' => $hasGradForm ? 'bg-success' : 'bg-warning',
                'description' => $hasGradForm ? 'Application Form uploaded' : 'Application Form required',
            ],
            [
                'name'        => 'Report of Grades (COG)',
                'completed'   => $hasROG,
                'status_text' => $hasROG ? 'Submitted' : 'Pending',
                'badge_class' => $hasROG ? 'bg-success' : 'bg-warning',
                'description' => $hasROG ? 'Report of Grades uploaded' : 'Report of Grades required',
            ],
        ];
    }

    /**
     * Save / update graduation requirements (called from modal)
     */
    public function saveRequirement(Request $request)
    {
        $request->validate([
            'GraduationForm_id'    => ['required', 'integer'],
            'Approval_Sheet'       => 'nullable|file|mimes:pdf,jpg,jpeg,png',
            'Certificate_Library'  => 'nullable|file|mimes:pdf,jpg,jpeg,png',
            'Barangay_Clearance'   => 'nullable|file|mimes:pdf,jpg,jpeg,png',
            'Birth_Certificate'    => 'nullable|file|mimes:pdf,jpg,jpeg,png',
            'applicationform_grad' => 'nullable|file|mimes:pdf,jpg,jpeg,png',
            'reportofgrade_path'   => 'nullable|file|mimes:pdf,jpg,jpeg,png',
            'remarks'              => 'nullable|string|max:500', // ✅ Add remarks validation
        ]);

        $loginId = optional($request->user())->Login_id
            ?? session('login_id')
            ?? session('Login_id');

        $student = $loginId ? StudentManage::where('Login_id', $loginId)->first() : null;
        if (!$student) {
            return back()->with('error', 'Student not found.');
        }

        $graduationForm = GraduationForm::where('Student_id', $student->Student_id)->first();
        if (!$graduationForm) {
            return back()->with('error', 'Graduation form not found.');
        }

        // Create or update the requirements row for this form
        $requirements = GraduationRequirement::firstOrNew([
            'GraduationForm_id' => $graduationForm->GraduationForm_id,
        ]);

        // Handle file uploads
        $fileFields = [
            'Approval_Sheet',
            'Certificate_Library',
            'Barangay_Clearance',
            'Birth_Certificate',
            'applicationform_grad',
            'reportofgrade_path',
        ];

        foreach ($fileFields as $field) {
            if ($request->hasFile($field)) {
                // optional: delete old file if it exists
                if (!empty($requirements->$field)) {
                    $this->deleteStorageFile($requirements->$field);
                }

                $path = $request->file($field)->store("graduation/{$student->SRCODE}", 'public');
                $requirements->$field = '/storage/' . $path;
            }
        }

        // ✅ NEW: Save remarks
        $requirements->remarks = $request->input('remarks');

        $requirements->GraduationForm_id = $graduationForm->GraduationForm_id;
        $requirements->save();

        return redirect()->route('student.graduation.status')
            ->with('success', 'Graduation requirements have been updated.');
    }

    /**
     * Delete graduation requirements row (called from Action -> Delete)
     */
    public function destroyRequirement(Request $request)
    {
        try {
            $loginId = optional($request->user())->Login_id
                ?? session('login_id')
                ?? session('Login_id');

            $student = $loginId ? StudentManage::where('Login_id', $loginId)->first() : null;
            if (!$student) {
                return redirect()->route('student.graduation.status')->with('error', 'Student not found.');
            }

            $graduationForm = GraduationForm::where('Student_id', $student->Student_id)->first();
            if (!$graduationForm) {
                return redirect()->route('student.graduation.status')->with('error', 'No graduation application found.');
            }

            // Delete graduation requirements first
            $requirements = GraduationRequirement::where('GraduationForm_id', $graduationForm->GraduationForm_id)->first();
            if ($requirements) {
                // Delete files from storage
                $fileFields = [
                    'Approval_Sheet',
                    'Certificate_Library',
                    'Barangay_Clearance',
                    'Birth_Certificate',
                    'applicationform_grad',
                    'reportofgrade_path',
                ];

                foreach ($fileFields as $field) {
                    if (!empty($requirements->$field)) {
                        $this->deleteStorageFile($requirements->$field);
                    }
                }
                $requirements->delete();
            }

            // Delete graduation form
            $graduationForm->delete();

            return redirect()->route('student.graduation.status')
                ->with('delete_success', 'Your graduation application has been successfully deleted.');

        } catch (\Exception $e) {
            return redirect()->route('student.graduation.status')
                ->with('error', 'Failed to delete graduation application: ' . $e->getMessage());
        }
    }

    /**
     * Download graduation documents
     */
    public function downloadDocuments(Request $request, $documentType)
    {
        $loginId = optional($request->user())->Login_id
            ?? session('login_id')
            ?? session('Login_id');

        $student = $loginId ? StudentManage::where('Login_id', $loginId)->first() : null;

        if (!$student) {
            return response()->json(['error' => 'Student not found.'], 404);
        }

        // STEP 1: Check Student_id in graduation_form
        $graduationForm = GraduationForm::where('Student_id', $student->Student_id)->first();

        if (!$graduationForm) {
            return response()->json(['error' => 'Graduation form not found.'], 404);
        }

        // STEP 2: Check GraduationForm_id in graduation_requirements
        $graduationRequirements = GraduationRequirement::where('GraduationForm_id', $graduationForm->GraduationForm_id)->first();

        if (!$graduationRequirements) {
            return response()->json(['error' => 'Graduation requirements not found.'], 404);
        }

        switch ($documentType) {
            case 'application-form':
                $path = $graduationForm->application_form_path;
                $filename = 'graduation_application_' . $student->SRCODE . '.pdf';
                break;
            case 'report-of-grades':
                $path = $graduationRequirements->reportofgrade_path ?? '';
                $filename = 'report_of_grades_' . $student->SRCODE . '.pdf';
                break;
            case 'cor':
                $path = $graduationRequirements->cor_path ?? '';
                $filename = 'cor_' . $student->SRCODE . '.pdf';
                break;
            default:
                return response()->json(['error' => 'Invalid document type.'], 400);
        }

        if (empty($path)) {
            return response()->json(['error' => 'Document not available.'], 404);
        }

        // Convert storage path to actual file path
        $filePath = $this->convertStoragePathToFilePath($path);

        if (!file_exists($filePath)) {
            return response()->json(['error' => 'File not found.'], 404);
        }

        return response()->download($filePath, $filename);
    }

    /**
     * Convert storage path to actual file path
     */
    private function convertStoragePathToFilePath($storagePath)
    {
        // Remove '/storage' prefix if present and convert to storage path
        if (strpos($storagePath, '/storage') === 0) {
            $relativePath = str_replace('/storage/', '', $storagePath);
            return storage_path('app/public/' . $relativePath);
        }

        return $storagePath;
    }

    /**
     * Helper: delete a file given a /storage/... URL
     */
    private function deleteStorageFile(string $url): void
    {
        if (strpos($url, '/storage/') === 0) {
            $relative = str_replace('/storage/', '', $url);
            Storage::disk('public')->delete($relative);
        }
    }

    /**
     * Print graduation status
     */
    public function printStatus(Request $request)
    {
        $loginId = optional($request->user())->Login_id
            ?? session('login_id')
            ?? session('Login_id');

        $student = $loginId ? StudentManage::where('Login_id', $loginId)->first() : null;

        if (!$student) {
            return redirect()->back()->with('error', 'Student not found.');
        }

        // STEP 1: Check Student_id in graduation_form
        $graduationForm = GraduationForm::where('Student_id', $student->Student_id)->first();

        if (!$graduationForm) {
            return redirect()->back()->with('error', 'Graduation form not found.');
        }

        // STEP 2: Check GraduationForm_id in graduation_requirements
        $graduationRequirements = GraduationRequirement::where('GraduationForm_id', $graduationForm->GraduationForm_id)->first();

        $programInfo = $this->getStudentProgramInfo($student);
        $graduationStatus = $this->determineGraduationStatus($graduationForm, $graduationRequirements);
        $requirementsChecklist = $this->getRequirementsChecklist($graduationRequirements);
        $remarks = $graduationRequirements ? $graduationRequirements->remarks : null;

        return view('student.print.graduationstatus', compact(
            'student',
            'graduationForm',
            'graduationRequirements',
            'programInfo',
            'graduationStatus',
            'requirementsChecklist',
            'remarks'
        ));
    }
}