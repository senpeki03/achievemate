<?php

namespace App\Http\Controllers\Programchair;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

// ✅ models
use App\Models\Curriculum;
use App\Models\CurriculumAy;
use App\Models\Campus;
use App\Models\College;
use App\Models\Program;
use App\Models\Major;
use App\Models\UserDesignation;   // ✅ add this
use App\Models\Designation;       // (optional, only if you use it)

class CurriculumController extends Controller
{
    public function entry()
    {
        // If there is already at least one Curriculum AY, go straight to the upload page
        if (CurriculumAy::query()->exists()) {
            return redirect()->route('programchair.curriculumupload');
        }

        // -------- Resolve current user's designation --------
        $loginId = session('Login_id'); // adjust if your session key differs

        $ud = $loginId
            ? UserDesignation::with('designation')
                ->where('Login_id', $loginId)
                ->latest('UserDesignation_id')
                ->first()
            : null;

        // ✅ Define BEFORE any use to avoid "Undefined variable"
        // Use nullsafe to avoid errors if $ud or relation is null
        $accessLevel = $ud?->designation?->Access ?? 'Campus';

        // Locks decide which dropdowns are shown/enabled
        $locks = [
            'campus'  => in_array($accessLevel, ['College','Program','Major'], true),
            'college' => in_array($accessLevel, ['College','Program','Major'], true),
            'program' => in_array($accessLevel, ['Program','Major'], true),
            'major'   => in_array($accessLevel, ['Major'], true),
        ];

        // Pre-fill ids from the user’s assignment (nullable)
        $prefill = [
            'Campus_id'  => $ud?->Campus_id,
            'College_id' => $ud?->College_id,
            'Program_id' => $ud?->Program_id,
            'Major_id'   => $ud?->Major_id,
        ];

        // -------- Filter dropdown options by scope --------
        $campuses = Campus::select('Campus_id','Campus_name')
            ->when($locks['campus'] && $prefill['Campus_id'], fn($q) => $q->where('Campus_id', $prefill['Campus_id']))
            ->get();

        $colleges = College::select('College_id','College_name','Campus_id')
            ->when($locks['college'] && $prefill['College_id'], fn($q) => $q->where('College_id', $prefill['College_id']))
            ->when($prefill['Campus_id'], fn($q) => $q->where('Campus_id', $prefill['Campus_id']))
            ->get();

        $programs = Program::select('Program_id','Program_name','College_id','Campus_id')
            ->when($locks['program'] && $prefill['Program_id'], fn($q) => $q->where('Program_id', $prefill['Program_id']))
            ->when($prefill['College_id'], fn($q) => $q->where('College_id', $prefill['College_id']))
            ->get();

        $majors = Major::select('Major_id','Major_name','Campus_id','College_id','Program_id')
            ->when($locks['major'] && $prefill['Major_id'], fn($q) => $q->where('Major_id', $prefill['Major_id']))
            ->when($prefill['Program_id'], fn($q) => $q->where('Program_id', $prefill['Program_id']))
            ->get();

        $curriculums = collect(); // safe default

        return view('programchair.curriculum', compact(
            'campuses','colleges','programs','majors','curriculums',
            'locks','prefill','accessLevel'
        ));
    }

    public function insert(Request $request)
    {
        $loginId = session('Login_id');
        $ud = $loginId
            ? UserDesignation::with('designation')
                ->where('Login_id', $loginId)
                ->latest('UserDesignation_id')
                ->first()
            : null;

        $accessLevel = $ud?->designation?->Access ?? 'Campus';
        $needProgram = in_array($accessLevel, ['Program','Major'], true);
        $needMajor   = ($accessLevel === 'Major');

        $request->validate([
            'campus_id'  => ['required','integer','min:1'],
            'college_id' => ['required','integer','min:1'],
            'program_id' => [$needProgram ? 'required' : 'nullable', 'integer', 'min:1'],
            'major_id'   => [$needMajor   ? 'required' : 'nullable', 'integer', 'min:1'],
        ]);

        $record = CurriculumAy::create([
            'Campus_id'  => (int)$request->campus_id,
            'College_id' => (int)$request->college_id,
            'Program_id' => $needProgram ? (int)$request->program_id : null,
            'Major_id'   => $needMajor   ? (int)$request->major_id   : null,
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
