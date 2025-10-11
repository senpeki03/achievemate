<?php

namespace App\Http\Controllers\registrar;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Student;
use App\Models\Login;

class ListController extends Controller
{
        public function destroy($id)
        {
            $student = Student::findOrFail($id);
            $loginId = $student->Login_id;

            // Delete the student
            $student->delete();

            // Delete corresponding login if it exists
            if ($loginId) {
                Login::where('Login_id', $loginId)->delete();
            }

            return response()->json(['success' => true]);
        }

        public function showStudentList()
        {
            $students = DB::table('studentmanagement')
                ->select('department', DB::raw('COUNT(*) as student_count'))
                ->whereNotNull('department')
                ->groupBy('department')
                ->orderBy('department')
                ->get();

            return view('registrar.studentlist', compact('students'));
        }

        public function showCollegeList($department)
        {
            $students = DB::table('studentmanagement')
                ->where('department', $department)
                ->get();

            return view('registrar.collegelist', compact('students', 'department'));
        }

        public function update(Request $request, $id)
        {
            $student = Student::findOrFail($id);
            $student->update($request->only([
                'srcode', 'firstname', 'middlename', 'lastname',
                'department', 'program'
            ]));

            return response()->json(['success' => true]);
        }

        public function showYearList($department)
        {
           $students = DB::table('studentmanagement')
            ->select('year', DB::raw('COUNT(*) as student_count'))
            ->where('department', $department)
            ->groupBy('year')
            ->orderByRaw("
                FIELD(
                    LOWER(year),
                    'first year',
                    'second year',
                    'third year',
                    'fourth year'
                )
            ")
            ->get();

           return view('registrar.yearlist', compact('students', 'department'));

        }


        public function showStudentsByYear($department, $year)
        {
            // Decode any '+' or '%20' into actual space
            $decodedYear = str_replace('+', ' ', urldecode($year));

            $students = DB::table('studentmanagement')
                ->where('department', $department)
                ->where('year', $decodedYear)
                ->get();

            return view('registrar.collegelist', compact('students', 'department', 'year'));
        }


        public function collegeList()
        {
            $departments = DB::table('studentmanagement')
            ->select('department', DB::raw('COUNT(*) as student_count'))
            ->whereNotNull('department')
            ->groupBy('department')
            ->orderBy('department')
            ->get();

        return view('registrar.studentlist', compact('departments')); // ✅ not students

        }

        public function showTrackList($department, $year)
        {
            $decodedYear = str_replace('+', ' ', urldecode($year)); // Fixes the + issue
            $normalizedYear = strtolower(trim($decodedYear));

            if (
                strtolower($department) !== 'cics' ||
                !in_array($normalizedYear, ['third year', '4th year', 'fourth year'])
            ) {
                return redirect()->route('registrar.students.by.year', [
                    'department' => $department,
                    'year' => $decodedYear
                ]);
            }

            $tracks = DB::table('studentmanagement')
                ->select('track', DB::raw('COUNT(*) as student_count'))
                ->where('department', $department)
                ->where('year', $decodedYear) // ✅ use decoded version here
                ->groupBy('track')
                ->get();

            return view('registrar.track', [
                'tracks' => $tracks,
                'department' => $department,
                'year' => $decodedYear
            ]);
        }



        public function showStudentsByTrack($department, $year, $track)
        {
            $decodedYear = str_replace('+', ' ', urldecode($year));
            $decodedTrack = str_replace('+', ' ', urldecode($track));

            $students = DB::table('studentmanagement')
                ->where('department', $department)
                ->where('year', $decodedYear)
                ->where('track', $decodedTrack)
                ->get();

            return view('registrar.collegelist', compact('students', 'department', 'year', 'track'));
        }


}

