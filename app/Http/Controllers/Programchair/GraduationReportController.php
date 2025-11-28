<?php

namespace App\Http\Controllers\ProgramChair;

use App\Http\Controllers\Controller;
use App\Models\StudentCourse;
use App\Models\UserDesignation;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class GraduationReportController extends Controller
{
    public function generateReport()
    {
        // 1) Current Program Chair
        $designation = UserDesignation::with(['college', 'program', 'major'])
            ->where('Login_id', auth()->id())
            ->firstOrFail();

        $program  = $designation->program;
        $major    = $designation->major;
        $college  = $designation->college;

        // 2) StudentCourses (including Major if set)
        $studentCourses = StudentCourse::with([
                'student.graduationForm.requirement',
                'program',
                'major',
            ])
            ->where('Program_id', $program->Program_id)
            ->when($major, fn($q) => $q->where('Major_id', $major->Major_id))
            ->get();

        // 3) Only "GRADUATING"
        $graduating = $studentCourses->filter(function ($sc) {
            $req = optional(optional($sc->student->graduationForm)->requirement);
            if (!$req || empty($req->remarks)) return false;
            return stripos($req->remarks, 'GRADUATING') !== false;
        });

        $students = $graduating->pluck('student')->filter()->values();

        // If Program Chair has no major, fallback to first student's major
        if (!$major) {
            $major = optional($graduating->first())->major;
        }

        // ------------------------------------------------------
        // 4) SIGNATORIES
        // ------------------------------------------------------

        /**
         * Format Signatory Name
         * - Title = normal case
         * - Name = ALL CAPS
         * - Middle initial = first letter + "."
         */
        $formatSignatory = function ($ud = null) {
            if (!$ud || !$ud->user) return null;

            $u = $ud->user;

            $first  = trim($u->First_name);
            $middle = trim($u->Middle_name);
            $last   = trim($u->Last_name);
            $title  = trim($u->Title);

            $mi = $middle !== '' ? ' ' . strtoupper(mb_substr($middle, 0, 1)) . '.' : '';

            // NAME PART ONLY (ALL CAPS)
            $nameCaps = strtoupper($first . $mi . ' ' . $last);

            // Title stays NORMAL CASE
            return trim($title . ' ' . $nameCaps);
        };

        // 4a) Program Chair (as Department Chair)
        $deptChair = UserDesignation::with(['user', 'designation'])
            ->where('College_id', $college->College_id)
            ->whereHas('designation', fn($q) => $q->where('Designation_name', 'LIKE', '%Program Chair%'))
            ->first();

        $deptChairName = $formatSignatory($deptChair) ?? 'Asst. Prof. BENJIE R. SAMONTE';
        $deptChairTitle = 'Department Chairperson, ITE Programs';

        // 4b) Dean
        $dean = UserDesignation::with(['user', 'designation'])
            ->where('College_id', $college->College_id)
            ->whereHas('designation', fn($q) => $q->where('Designation_name', 'LIKE', '%Dean%'))
            ->first();

        $deanName = $formatSignatory($dean) ?? 'Dr. LORISSA J. BUENAS';
        $deanTitle = optional(optional($dean)->designation)->Designation_name ?? 'Dean, CICS';

        $signatories = [
            'dept_chair_name'  => $deptChairName,
            'dept_chair_title' => $deptChairTitle,
            'dean_name'        => $deanName,
            'dean_title'       => $deanTitle,
        ];

        // 5) Today’s Date
        $today = Carbon::now()->format('F d, Y');

        // 6) Generate PDF (Long Bond 8.5x13)
        $pdf = Pdf::loadView('reports.graduation', [
            'students'     => $students,
            'program'      => $program,
            'major'        => $major,
            'college'      => $college,
            'signatories'  => $signatories,
            'today'        => $today,
        ])->setPaper([0, 0, 612, 936], 'portrait');

        return $pdf->download('Graduation-Report.pdf');
    }
}
