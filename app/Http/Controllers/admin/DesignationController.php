<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Designation;

class DesignationController extends Controller
{
    // Show all designations with custom sorting
    public function index()
    {
        $designations = Designation::orderByRaw("
            CASE
                WHEN Designation_name = 'Vice Chancellor for Academic Affairs' THEN 1
                WHEN Designation_name = 'Dean' THEN 2
                WHEN Designation_name = 'Program Chair' THEN 3
                ELSE 4
            END
        ")
        ->get();  // Fetch all designations sorted by custom order

        return view('admin.designation', compact('designations'));
    }

    // Store new designation
    public function store(Request $request)
    {
        $request->validate([
            'Designation_name' => 'required|string|max:255',
            'Access' => 'required|string|max:255',
        ]);

        Designation::create([
            'Designation_name' => $request->Designation_name,
            'Access' => $request->Access
        ]);

        return response()->json(['success' => true]);
    }

    // Update existing designation
    public function update(Request $request, $id)
    {
        $request->validate([
            'Designation_name' => 'required|string|max:255',
            'Access' => 'required|string|max:255',
        ]);

        $designation = Designation::findOrFail($id);
        $designation->update([
            'Designation_name' => $request->Designation_name,
            'Access' => $request->Access
        ]);

        return response()->json(['success' => true]);
    }

    // Delete a designation
    public function destroy($id)
    {
        $designation = Designation::findOrFail($id);
        $designation->delete();

        return response()->json(['success' => true]);
    }
}
