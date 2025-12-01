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

        // 2) Base query for students (same filters as before)
        $studentsQuery = StudentManage::query()
            ->with([
                'studentCourse' => function ($q) use ($designation) {
                    $q->where('College_id', $designation->College_id)
                      ->where('Program_id', $designation->Program_id)
                      ->with('major');

                    if (!empty($designation->Major_id)) {
                        $q->where('Major_id', $designation->Major_id);
                    }
                },
                'graduationForm.requirement', // eager load
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
            ->orderBy('First_name');

        // 3) Paginate — 30 students per page
        $studentsPaginated = $studentsQuery->paginate(30);

        // Current page collection (Collection, not paginator)
        $pageStudents = $studentsPaginated->getCollection();

        // 4) Group students BY YEAR + MAJOR for *this page only*
        $groups = $pageStudents->groupBy(function ($stu) {
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

        // 5) Order groups: FOURTH YEAR first, then THIRD YEAR
        //    Inside each year: Business Analytics, then others A–Z, then No Major
        $groups = $groups->sortBy(function ($students, $groupKey) {
            [$year, $majorName] = explode('|', $groupKey, 2);

            // Year ordering
            $yearOrder = ($year === 'FOURTH YEAR') ? '0' : '1';

            // Major ordering within year
            if (strcasecmp($majorName, 'Business Analytics') === 0) {
                $majorOrder = '0';
            } elseif (strcasecmp($majorName, 'No Major') === 0) {
                $majorOrder = '2_' . $majorName;
            } else {
                $majorOrder = '1_' . strtolower($majorName);
            }

            return $yearOrder . '_' . $majorOrder;
        });

        return view('programchair.graduation', [
            'designation'       => $designation,
            'groups'            => $groups,            // grouped per page
            'studentsPaginated' => $studentsPaginated, // for pagination + row numbers
        ]);
    }
}
