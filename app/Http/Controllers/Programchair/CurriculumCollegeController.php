<?php

namespace App\Http\Controllers\Programchair;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use App\Models\Curriculum;
use App\Models\Curriculumsubject;
use App\Models\CurriculumAy;
use App\Models\Campus;
use App\Models\College;
use App\Models\Program;
use App\Models\Major;

class CurriculumCollegeController extends Controller
{
    /**
     * Show the upload page.
     * If there is NO Curriculum AY yet, redirect the user to the AY create page.
     */
    public function showUploadForm()
    {
        // 👉 Guard: if no AY exists, go to the Curriculum (AY create) page
        if (!CurriculumAy::query()->exists()) {
            return redirect()->route('programchair.curriculum');
        }

        // Build list/table data
        $curriculums = Curriculum::join('curriculum_ay', 'curriculum.CurriculumAY_id', '=', 'curriculum_ay.CurriculumAY_id')
            ->join('program', 'curriculum_ay.Program_id', '=', 'program.Program_id')
            ->join('college', 'curriculum_ay.College_id', '=', 'college.College_id')
            ->join('campus', 'curriculum_ay.Campus_id', '=', 'campus.Campus_id')
            ->select(
                'curriculum.*',
                'curriculum_ay.Academic_year',
                'program.Program_name',
                'college.College_name',
                'campus.Campus_name'
            )
            ->get();

        $curriculum_ays = CurriculumAy::with(['campus', 'college', 'program'])->get();

        // For the "Add Curriculum" modal on this page
        $campuses = Campus::select('Campus_id', 'Campus_name')->get();
        $colleges = College::select('College_id', 'College_name', 'Campus_id')->get();
        $programs = Program::select('Program_id', 'Program_name', 'College_id', 'Campus_id')->get();
        $majors   = Major::select('Major_id', 'Major_name', 'Campus_id', 'College_id', 'Program_id')->get();

        return view('programchair.curriculumupload', compact(
            'curriculums', 'curriculum_ays', 'campuses', 'colleges', 'programs', 'majors'
        ));
    }

    /**
     * Upload a PDF and run OCR -> insert curriculum subjects.
     */
    public function upload(Request $request)
    {
        try {
            $request->validate([
                'pdf'              => 'required|mimes:pdf|max:2048',
                'curriculum_ay_id' => 'required|exists:curriculum_ay,CurriculumAY_id',
            ]);

            $file = $request->file('pdf');
            $pdfName = time() . '_' . $file->getClientOriginalName();

            $destinationPath = public_path('uploads');
            if (!File::exists($destinationPath)) {
                File::makeDirectory($destinationPath, 0755, true);
            }

            $file->move($destinationPath, $pdfName);
            $absolutePath = $destinationPath . DIRECTORY_SEPARATOR . $pdfName;

            // Save raw binary (LONGBLOB)
            $fileData = file_get_contents($absolutePath);

            $curriculum = Curriculum::create([
                'CurriculumAY_id' => $request->curriculum_ay_id,
                'Curriculum_name' => $pdfName,
                'File_data'       => $fileData,
            ]);

            $curriculumId = $curriculum->curriculum_id;

            // Run Python OCR
            $scriptPath = base_path('storage/app/ocr_curriculum.py');
            shell_exec("python " . escapeshellarg($scriptPath) . ' ' . escapeshellarg($absolutePath));

            // Wait briefly for OCR result
            $ocrResultPath = base_path('logs/ocr_result.txt');
            $timeout = 5;
            $start = time();
            while (!file_exists($ocrResultPath) && (time() - $start) < $timeout) {
                usleep(200000);
            }

            if (!file_exists($ocrResultPath)) {
                throw new \Exception("❌ OCR result not found.");
            }

            $json = json_decode(file_get_contents($ocrResultPath), true);
            $subjects = $json['subjects'] ?? [];

            if (empty($subjects)) {
                throw new \Exception("❌ No subjects found in OCR result.");
            }

            foreach ($subjects as $group) {
                $codes    = $group['Code'] ?? [];
                $titles   = $group['Course_Title'] ?? [];
                $units    = $group['units'] ?? [];
                $lecs     = $group['lec'] ?? [];
                $labs     = $group['lab'] ?? [];
                $prereqs  = $group['prerequisite'] ?? [];

                $total = min(count($codes), count($titles), count($units), count($lecs), count($labs), count($prereqs));

                for ($i = 0; $i < $total; $i++) {
                    $code  = $codes[$i]  ?? null;
                    $title = $titles[$i] ?? null;
                    if (!$code || !$title || strtolower(trim($code)) === 'code') continue;

                    Curriculumsubject::create([
                        'curriculum_id' => $curriculumId,
                        'Code'          => $code,
                        'Course_Title'  => $title,
                        'units'         => $units[$i] ?? 0,
                        'lec'           => $lecs[$i] ?? 0,
                        'lab'           => $labs[$i] ?? 0,
                        'prerequisite'  => $prereqs[$i] ?? '-',
                        'total_units'   => $group['total_units'] ?? 0,
                        'total_lec'     => $group['total_lec'] ?? 0,
                        'total_lab'     => $group['total_lab'] ?? 0,
                        'year_level'    => $group['year_level'] ?? 'N/A',
                        'semester'      => $group['semester'] ?? 'N/A',
                        'track'         => substr($group['track'] ?? 'N/A', 0, 100),
                    ]);
                }
            }

            if ($request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Curriculum uploaded.',
                ]);
            }

            return redirect()
                ->route('programchair.curriculumupload')
                ->with('imported', true);

        } catch (\Throwable $e) {
            Log::error('❌ Upload Error: ' . $e->getMessage());

            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Upload failed. ' . $e->getMessage(),
                ], 500);
            }

            return redirect()
                ->route('programchair.curriculumupload')
                ->with('error', 'Upload failed. ' . $e->getMessage());
        }
    }

    /**
     * Inline view of the stored PDF.
     */
    public function view($curriculum_id)
    {
        $curriculum = Curriculum::findOrFail($curriculum_id);

        return response($curriculum->File_data)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="' . $curriculum->Curriculum_name . '"');
    }

    /**
     * Delete curriculum (JSON for AJAX; redirect otherwise).
     */
    public function destroy($curriculum_id)
    {
        $curriculum = Curriculum::findOrFail($curriculum_id);
        $curriculum->delete();

        if (request()->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Curriculum deleted.',
            ]);
        }

        return redirect()
            ->route('programchair.curriculumupload')
            ->with('deleted', true);
    }

    public function destroyAy($id)
{
    $ay = \App\Models\CurriculumAy::find($id);
    if (!$ay) {
        return response()->json(['success' => false, 'message' => 'Record not found.'], 404);
    }

    // Prevent deletion if used by any curriculum (optional safety)
    if (\App\Models\Curriculum::where('CurriculumAY_id', $id)->exists()) {
        return response()->json([
            'success' => false,
            'message' => 'Cannot delete: there are curriculums linked to this AY.'
        ], 409);
    }

    $ay->delete();
    return response()->json(['success' => true]);
}

}
