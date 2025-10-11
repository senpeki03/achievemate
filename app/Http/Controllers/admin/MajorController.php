<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Program;
use App\Models\Campus;
use App\Models\College;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Validation\Rule;

class MajorController extends Controller
{
    public function index()
    {
        $campuses = DB::table('campus')->get();
        $colleges = DB::table('college')->get();
        $programs = DB::table('program')->get();

        $majors = DB::table('major')
            ->join('campus', 'campus.Campus_id', '=', 'major.Campus_id')
            ->join('college', 'college.College_id', '=', 'major.College_id')
            ->join('program', 'program.Program_id', '=', 'major.Program_id')
            ->select(
                'major.*',
                'campus.Campus_name',
                'college.Abbreviation as College_abbr',
                'program.Abbreviation as Program_abbr'
            )
            ->get();

        return view('admin.major', compact('campuses', 'colleges', 'programs', 'majors'));
    }

    public function store(Request $request)
    {
        DB::table('major')->insert([
            'Campus_id' => $request->campus_id,
            'College_id' => $request->college_id,
            'Program_id' => $request->program_id,
            'Major_name' => $request->major_name,
            'Abbreviation' => $request->abbreviation,
            'Created_at' => now()->toDateString() // Format: YYYY-MM-DD
        ]);

        return response()->json(['success' => true]);
    }

    public function update(Request $request, $id)
    {
        DB::table('major')->where('Major_id', $id)->update([
            'Campus_id' => $request->campus_id,
            'College_id' => $request->college_id,
            'Program_id' => $request->program_id,
            'Major_name' => $request->major_name,
            'Abbreviation' => $request->abbreviation
            // Don't include updated_at if it's not in the table
        ]);

        return response()->json(['success' => true]);
    }

    public function destroy($id)
    {
        DB::table('major')->where('Major_id', $id)->delete();
        return response()->json(['success' => true]);
    }


}