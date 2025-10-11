<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\College;
use App\Models\Campus;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Validation\Rule;

class CollegeController extends Controller
{
    public function index()
    {
        $colleges = DB::table('college')
            ->join('campus', 'college.Campus_id', '=', 'campus.Campus_id')
            ->select('college.*', 'campus.Campus_name')
            ->get();

        $campuses = DB::table('campus')->get();

        return view('admin.college', compact('colleges', 'campuses'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => [
                'required',
                // ✅ Check unique combination: College_name + Campus_id
                Rule::unique('college', 'College_name')->where(function ($query) use ($request) {
                    return $query->where('Campus_id', $request->campus_id);
                }),
            ],
            'campus_id' => 'required|exists:campus,Campus_id',
            'abbreviation' => 'required|string|max:50',
        ], [
            'name.unique' => 'This college is already added in the selected campus.',
        ]);

        College::create([
            'Abbreviation' => $request->abbreviation,
            'College_name' => $request->name,
            'Campus_id' => $request->campus_id,
            'Created_at' => Carbon::now(),
        ]);

        return redirect()->route('admin.college')->with('added', true);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => [
                'required',
                Rule::unique('college', 'College_name')->where(function ($query) use ($request, $id) {
                    return $query->where('Campus_id', $request->campus_id)
                                 ->where('College_id', '!=', $id); // Avoid self during update
                }),
            ],
            'campus_id' => 'required|exists:campus,Campus_id',
            'abbreviation' => 'required|string|max:50',
        ], [
            'name.unique' => 'This college is already added in the selected campus.',
        ]);

        $college = College::findOrFail($id);
        $college->update([
            'College_name' => $request->name,
            'Campus_id' => $request->campus_id,
            'Abbreviation' => $request->abbreviation,
        ]);

        return redirect()->route('admin.college')->with('updated', true);
    }

    public function destroy($id)
    {
        $college = College::findOrFail($id);
        $college->delete();

        return redirect()->route('admin.college')->with('deleted', true);
    }
}
