<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\College;
use App\Models\Campus;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;

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
                Rule::unique('college', 'College_name')->where(function ($query) use ($request) {
                    return $query->where('Campus_id', $request->campus_id);
                }),
            ],
            'campus_id'    => 'required|exists:campus,Campus_id',
            'abbreviation' => 'required|string|max:50',
            'logo'         => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ], [
            'name.unique' => 'This college is already added in the selected campus.',
        ]);

        // ✅ default to empty string so NOT NULL column is always satisfied
        $logoPath = '';

        if ($request->hasFile('logo')) {
            $logoPath = $request->file('logo')->store('college_logos', 'public');
        }

        College::create([
            'Abbreviation' => $request->abbreviation,
            'College_name' => $request->name,
            'Campus_id'    => $request->campus_id,
            'Logo'         => $logoPath,       // ✅ use correct column name (capital L)
            'Created_at'   => Carbon::now(),
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
                                 ->where('College_id', '!=', $id);
                }),
            ],
            'campus_id'    => 'required|exists:campus,Campus_id',
            'abbreviation' => 'required|string|max:50',
            'logo'         => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ], [
            'name.unique' => 'This college is already added in the selected campus.',
        ]);

        $college = College::findOrFail($id);

        // ✅ Handle logo replacement (correct property name: Logo)
        if ($request->hasFile('logo')) {

            if (!empty($college->Logo) && Storage::disk('public')->exists($college->Logo)) {
                Storage::disk('public')->delete($college->Logo);
            }

            $college->Logo = $request->file('logo')->store('college_logos', 'public');
        }

        $college->College_name = $request->name;
        $college->Campus_id    = $request->campus_id;
        $college->Abbreviation = $request->abbreviation;
        $college->save();

        return redirect()->route('admin.college')->with('updated', true);
    }

    public function destroy($id)
    {
        $college = College::findOrFail($id);

        // ✅ delete logo file if it exists (again, correct property name)
        if (!empty($college->Logo) && Storage::disk('public')->exists($college->Logo)) {
            Storage::disk('public')->delete($college->Logo);
        }

        $college->delete();

        return redirect()->route('admin.college')->with('deleted', true);
    }
}
