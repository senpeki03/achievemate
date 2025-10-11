<?php

namespace App\Http\Controllers\Programchair;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;

use App\Models\Curriculum;      // ✅ model import
use App\Models\CurriculumAy;
use App\Models\Campus;
use App\Models\College;
use App\Models\Program;
use App\Models\Major;

class CurriculumController extends Controller
{
    public function entry()
    {
        // If there is already at least one Curriculum AY, go straight to the upload page
        if (CurriculumAy::query()->exists()) {
            return redirect()->route('programchair.curriculumupload');
        }

        // Show the AY creation page with dropdown data
        $campuses = Campus::select('Campus_id','Campus_name')->get();
        $colleges = College::select('College_id','College_name','Campus_id')->get();
        $programs = Program::select('Program_id','Program_name','College_id','Campus_id')->get();
        $majors   = Major::select('Major_id','Major_name','Campus_id','College_id','Program_id')->get();

        $curriculums = collect(); // safe default

        return view('programchair.curriculum', compact(
            'campuses','colleges','programs','majors','curriculums'
        ));
    }

    // Create a Curriculum AY
    public function insert(Request $request)
    {
        try {
            $request->validate([
                'campus_id'     => 'required|integer',
                'college_id'    => 'required|integer',
                'program_id'    => 'required|integer',
                'major_id'      => 'required|integer',
                'academic_year' => 'required|string',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors'  => $e->errors(),
            ], 422);
        }

        $record = CurriculumAy::create([
            'Campus_id'     => $request->campus_id,
            'College_id'    => $request->college_id,
            'Program_id'    => $request->program_id,
            'Major_id'      => $request->major_id,
            'Academic_year' => $request->academic_year,
        ]);

        session(['curriculum_ay_id' => $record->CurriculumAY_id]);

        return response()->json([
            'success'  => true,
            'message'  => 'Record inserted.',
            'redirect' => route('programchair.curriculumupload'),
        ]);
    }

    // Delete a curriculum file/record
    public function destroy($id)
    {
        $curriculum = Curriculum::find($id);
        if (!$curriculum) {
            return response()->json([
                'success' => false,
                'message' => 'Record not found.',
            ], 404);
        }

        // If you saved a copy in /public/uploads (your upload code does), delete it
        $diskPath = public_path('uploads/' . $curriculum->Curriculum_name);
        if (File::exists($diskPath)) {
            @File::delete($diskPath);
        }

        $curriculum->delete();

        if (request()->ajax()) {
            return response()->json(['success' => true]);
        }

        return back()->with('deleted', true);
    }
}
