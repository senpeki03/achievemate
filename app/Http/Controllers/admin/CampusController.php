<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\Campus;
use Carbon\Carbon;

class CampusController extends Controller
{
    public function index()
    {
        $campuses = Campus::all(); // ✅ Use 'campuses' not 'add_campus'
        return view('admin.campus', compact('campuses'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'location' => 'required|string|max:255',
        ]);

        Campus::create([
            'Campus_name' => $request->name,
            'Location' => $request->location,
            'Created_at' => Carbon::now()->format('Y-m-d')
        ]);

        // ✅ USE 'added' TO MATCH YOUR BLADE
        return redirect()->route('admin.campus')->with('added', true);
    }

    public function destroy($id)
    {
        $campus = Campus::findOrFail($id);
        $campus->delete();

        // ✅ USE 'deleted' TO MATCH YOUR BLADE
        return redirect()->route('admin.campus')->with('deleted', true);
    }

    // Optionally add update later:
    public function update(Request $request, $id)
    {
        $campus = Campus::findOrFail($id);
        $campus->update([
            'Campus_name' => $request->name,
            'Location' => $request->location,
        ]);

        // ✅ USE 'updated' TO MATCH YOUR BLADE
        return redirect()->route('admin.campus')->with('updated', true);
    }

}

