<?php

namespace App\Http\Controllers\ProgramChair;

use App\Http\Controllers\Controller;
use App\Models\UserDesignation;
use App\Models\StudentManage;

class GraduationController extends Controller
{
    public function index()
    {
        $loginId = auth()->user()->Login_id ?? session('Login_id');

        if (!$loginId) {
            return redirect()->route('login')->with('error', 'Please log in first.');
        }

        // 1) Program Chair info (pang-filter lang)
        $designation = UserDesignation::with(['college', 'program', 'major'])
            ->where('Login_id', $loginId)
            ->firstOrFail();

        // 2) Students: same College/Program/(Major of chair, if any) + BOTH THIRD YEAR AND FOURTH YEAR
        $students = StudentManage::query()
            ->with([
                'studentCourse' => function ($q) use ($designation) {
                    $q->where('College_id', $designation->College_id)
                      ->where('Program_id', $designation->Program_id)
                      ->with('major');

                    if (!empty($designation->Major_id)) {
                        $q->where('Major_id', $designation->Major_id);
                    }
                },
                'graduationForm.requirement', // Eager load the relationship
            ])
            ->whereHas('studentCourse', function ($q) use ($designation) {
                $q->where('College_id', $designation->College_id)
                  ->where('Program_id', $designation->Program_id);

                if (!empty($designation->Major_id)) {
                    $q->where('Major_id', $designation->Major_id);
                }
            })
            ->where(function ($query) {
                $query->whereIn('Year', ['THIRD YEAR', 'Third Year', '3rd Year'])
                      ->orWhereIn('Year', ['FOURTH YEAR', 'Fourth Year', '4th Year']);
            })
            ->orderByRaw("
                CASE 
                    WHEN Year IN ('FOURTH YEAR', 'Fourth Year', '4th Year') THEN 1
                    WHEN Year IN ('THIRD YEAR', 'Third Year', '3rd Year') THEN 2
                    ELSE 3
                END
            ")
            ->orderBy('Last_name')
            ->orderBy('First_name')
            ->get();

        // 3) Group students by YEAR first, then by MAJOR
        $groups = $students->groupBy(function ($stu) {
            // Normalize year values
            $year = strtoupper(trim($stu->Year));
            if (in_array($year, ['FOURTH YEAR', '4TH YEAR'])) {
                $yearGroup = 'FOURTH YEAR';
            } else {
                $yearGroup = 'THIRD YEAR';
            }

            $course = $stu->studentCourse->first();
            $name   = $course?->major?->Major_name;

            if (!$name || trim($name) === '' || strcasecmp($name, 'none') === 0) {
                return $yearGroup . '|No Major';
            }

            return $yearGroup . '|' . $name;
        });

        // 4) Order groups: FOURTH YEAR first, then THIRD YEAR
        // Within each year: Business Analytics first, then other majors alpha, last 'No Major'
        $groups = $groups->sortBy(function ($students, $groupKey) {
            list($year, $majorName) = explode('|', $groupKey, 2);
            
            // Year ordering: FOURTH YEAR first (0), THIRD YEAR second (1)
            $yearOrder = ($year === 'FOURTH YEAR') ? '0' : '1';
            
            // Major ordering within year
            if (strcasecmp($majorName, 'Business Analytics') === 0) {
                $majorOrder = '0';             // pinaka-una
            } elseif (strcasecmp($majorName, 'No Major') === 0) {
                $majorOrder = '2_'.$majorName; // pinaka-huli
            } else {
                $majorOrder = '1_'.strtolower($majorName); // ibang majors in between
            }

            return $yearOrder . '_' . $majorOrder;
        });

        return view('programchair.graduation', [
            'designation'     => $designation,
            'groups'          => $groups,    // Collection keyed by "YEAR|MAJOR"
        ]);
    }
}