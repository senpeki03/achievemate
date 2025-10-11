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

class ProgramController extends Controller
{
    public function index()
    {
        $programs = DB::table('program')
            ->join('campus', 'program.Campus_id', '=', 'campus.Campus_id')
            ->join('college', 'program.College_id', '=', 'college.College_id')
            ->select(
                'program.*',
                'campus.Campus_name',
                'college.College_name',
                'college.Abbreviation as College_Abbreviation'
            )
            ->get();

        $campuses = DB::table('campus')
            ->select('Campus_id', 'Campus_name')
            ->get();

        $colleges = DB::table('college')
            ->select('College_id', 'College_name', 'Campus_id')
            ->get();

        return view('admin.program', compact('programs', 'campuses', 'colleges'));
    }



    public function store(Request $request)
    {
        $request->validate([
            'name' => [
                'required',
                Rule::unique('program', 'Program_name')->where(function ($query) use ($request) {
                    return $query->where('Campus_id', $request->campus_id)
                                 ->where('College_id', $request->college_id);
                }),
            ],
            'campus_id' => 'required|exists:campus,Campus_id',
            'college_id' => 'required|exists:college,College_id',
            'abbreviation' => 'required|string|max:50',
        ], [
            'name.unique' => 'This program is already added in the selected college and campus.',
        ]);

        Program::create([
            'Campus_id' => $request->campus_id,
            'College_id' => $request->college_id,
            'Abbreviation' => $request->abbreviation,
            'Program_name' => $request->name,
            'Created_at' => Carbon::now(),
        ]);

        return redirect()->route('admin.program')->with('added', true);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => [
                'required',
                Rule::unique('program', 'Program_name')->where(function ($query) use ($request, $id) {
                    return $query->where('Campus_id', $request->campus_id)
                                 ->where('College_id', $request->college_id)
                                 ->where('Program_id', '!=', $id);
                }),
            ],
            'campus_id' => 'required|exists:campus,Campus_id',
            'college_id' => 'required|exists:college,College_id',
            'abbreviation' => 'required|string|max:50',
        ], [
            'name.unique' => 'This program is already added in the selected college and campus.',
        ]);

        $program = Program::findOrFail($id);
        $program->update([
            'Program_name' => $request->name,
            'Campus_id' => $request->campus_id,
            'College_id' => $request->college_id,
            'Abbreviation' => $request->abbreviation,
        ]);

        return redirect()->route('admin.program')->with('updated', true);
    }

    public function destroy($id)
    {
        $program = Program::findOrFail($id);
        $program->delete();

        return redirect()->route('admin.program')->with('deleted', true);
    }
}
