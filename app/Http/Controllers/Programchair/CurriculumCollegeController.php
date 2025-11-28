<?php

namespace App\Http\Controllers\Programchair;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Smalot\PdfParser\Parser;

// Models
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
     * Show the upload page (works even if CurriculumAY_id is NULL on curriculums).
     */
    public function showUploadForm()
    {
        $curriculums = Curriculum::leftJoin('curriculum_ay', 'curriculum.CurriculumAY_id', '=', 'curriculum_ay.CurriculumAY_id')
            ->leftJoin('program', 'curriculum_ay.Program_id', '=', 'program.Program_id')
            ->leftJoin('college', 'curriculum_ay.College_id', '=', 'college.College_id')
            ->leftJoin('campus', 'curriculum_ay.Campus_id', '=', 'campus.Campus_id')
            ->select([
                'curriculum.curriculum_id',
                'curriculum.Curriculum_name',
                'curriculum.CurriculumAY_id',
                'curriculum.Academic_year',
                'program.Program_name',
                'college.College_name',
                'campus.Campus_name',
            ])
            ->orderByDesc('curriculum.curriculum_id')
            ->get();

        // Keep datasets in case you still use them elsewhere (e.g., Add Curriculum modal)
        $curriculum_ays = collect();
        $campuses = Campus::select('Campus_id', 'Campus_name')->get();
        $colleges = College::select('College_id', 'College_name', 'Campus_id')->get();
        $programs = Program::select('Program_id', 'Program_name', 'College_id', 'Campus_id')->get();
        $majors   = Major::select('Major_id', 'Major_name', 'Campus_id', 'College_id', 'Program_id')->get();

        return view('programchair.curriculumupload', compact(
            'curriculums', 'curriculum_ays', 'campuses', 'colleges', 'programs', 'majors'
        ));
    }

    /**
     * Upload a PDF + parse. Academic Year (string) required; Curriculum AY id optional.
     * If no curriculum_ay_id is posted and there is exactly ONE AY row in DB, auto-use it.
     */
    public function upload(Request $request)
    {
        try {
            $request->validate([
                'pdf'              => 'required|mimes:pdf|max:2048',
                'academic_year'    => 'required|string',
                'curriculum_ay_id' => 'nullable|exists:curriculum_ay,CurriculumAY_id',
            ]);

            // Pick AY id: prefer posted value; else if there's exactly one AY row, auto-pick it.
            $ayId = $request->input('curriculum_ay_id');
            if (!$ayId && CurriculumAy::count() === 1) {
                $ayId = CurriculumAy::value('CurriculumAY_id');
            }

            // Save file to /public/uploads
            $file    = $request->file('pdf');
            $pdfName = time() . '_' . $file->getClientOriginalName();

            $destinationPath = public_path('uploads');
            if (!File::exists($destinationPath)) {
                File::makeDirectory($destinationPath, 0755, true);
            }
            $file->move($destinationPath, $pdfName);
            $absolutePath = $destinationPath . DIRECTORY_SEPARATOR . $pdfName;

            DB::beginTransaction();

            // Store file binary in DB
            $fileData   = file_get_contents($absolutePath);
            $curriculum = Curriculum::create([
                'CurriculumAY_id' => $ayId ?: null,
                'Curriculum_name' => $pdfName,
                'File_data'       => $fileData,
                'Academic_year'   => $request->input('academic_year'),
            ]);

            // Eloquent returns lowercase PK attribute by default (per your table)
            $curriculumId = (int) data_get($curriculum, 'curriculum_id');
            if (!$curriculumId) {
                throw new \RuntimeException('Failed to get curriculum_id after create().');
            }

            // ===== PDF parse (PHP parser) =====
            $parser = new Parser();
            $pdfObj = $parser->parseFile($absolutePath);
            $pages  = $pdfObj->getPages();

            $START_PAGE = 2;
            $END_PAGE   = 9;

            $groupedSubjects = [];
            $flatRows = [];
            $group = ['Code'=>[], 'Course_Title'=>[], 'units'=>[], 'lec'=>[], 'lab'=>[], 'prerequisite'=>[]];
            $currentTrack = null; $currentYear = null; $currentSemester = null;

            $logsDir = storage_path('logs');
            File::ensureDirectoryExists($logsDir);
            $logPath           = $logsDir . '/curriculum_log.txt';
            $groupedResultPath = $logsDir . '/ocr_result.txt';
            $flatResultPath    = $logsDir . '/flat_subjects.txt';
            $rawTextLog        = '';

            $flushGroup = function (array $totals = [0,0,0]) use (&$groupedSubjects, &$group, &$currentYear, &$currentSemester, &$currentTrack) {
                if (empty($group['Code'])) return;
                $groupedSubjects[] = [
                    'Code'          => $group['Code'],
                    'Course_Title'  => $group['Course_Title'],
                    'units'         => $group['units'],
                    'lec'           => $group['lec'],
                    'lab'           => $group['lab'],
                    'prerequisite'  => $group['prerequisite'],
                    'total_units'   => $totals[0] ?: array_sum($group['units']),
                    'total_lec'     => $totals[1] ?: array_sum($group['lec']),
                    'total_lab'     => $totals[2] ?: array_sum($group['lab']),
                    'year_level'    => $currentYear,
                    'semester'      => $currentSemester,
                    'track'         => $currentTrack,
                ];
                $group = ['Code'=>[], 'Course_Title'=>[], 'units'=>[], 'lec'=>[], 'lab'=>[], 'prerequisite'=>[]];
            };

            $extractTotals = function (string $line) {
                if (preg_match('/TOTAL\s+(\d+)\s+(\d+)\s+(\d+)/i', $line, $m)) {
                    return [(int) $m[1], (int) $m[2], (int) $m[3]];
                }
                return [0,0,0];
            };

            for ($p = $START_PAGE; $p < min($END_PAGE, count($pages)); $p++) {
                $lines = preg_split('/\R/u', trim($pages[$p]->getText()));
                $rawTextLog .= implode("\n", $lines) . "\n";

                for ($i = 0; $i < count($lines); ) {
                    $line = trim($lines[$i]);

                    if ($line === '' || preg_match('/^Page\s+\d+/i', $line)) { $i++; continue; }

                    if (stripos($line, 'program of study') !== false) {
                        if (preg_match('/\((.*?)\)/', $line, $m)) $currentTrack = trim($m[1]); else $currentTrack = 'Unknown';
                        $i++; continue;
                    }
                    if (stripos($line, 'first year')   !== false) { $currentYear = 'First Year';  $i++; continue; }
                    if (stripos($line, 'second year')  !== false) { $currentYear = 'Second Year'; $i++; continue; }
                    if (stripos($line, 'third year')   !== false) { $currentYear = 'Third Year';  $i++; continue; }
                    if (stripos($line, 'fourth year')  !== false) { $currentYear = 'Fourth Year'; $i++; continue; }
                    if (stripos($line, 'first semester')  !== false) { $currentSemester = 'First Semester';  $i++; continue; }
                    if (stripos($line, 'second semester') !== false) { $currentSemester = 'Second Semester'; $i++; continue; }

                    if (preg_match('/^TOTAL/i', $line)) {
                        $totals = $extractTotals($line);
                        $flushGroup($totals);
                        $i++; continue;
                    }

                    if (preg_match('/code|course title|units|lec|lab|prerequisite/i', $line)) { $i++; continue; }
                    if (preg_match('/^\d+\s*$/', $line)) { $i++; continue; }

                    if ($i + 4 < count($lines)) {
                        $l0 = trim($lines[$i]);
                        $l1 = trim($lines[$i+1]);
                        $l2 = trim($lines[$i+2]);
                        $l3 = trim($lines[$i+3]);
                        $l4 = trim($lines[$i+4]);

                        $code=null; $title=null; $units=0; $lec=0; $lab=0; $prereq='-';
                        $skip=6;

                        if (preg_match('/^([A-Z]{2,}\s*\d{3})\s+(.+)/', $l0, $m)) {
                            $code  = $m[1];
                            $title = $m[2];
                            if (preg_match('/^[A-Z]{2,}\s*\d{3}$/', $title)) { $i++; continue; }
                            $units  = ctype_digit($l1) ? (int)$l1 : 0;
                            $lec    = ctype_digit($l2) ? (int)$l2 : 0;
                            $lab    = ctype_digit($l3) ? (int)$l3 : 0;
                            $prereq = $l4 ?: '-';
                            $skip   = 5;
                        } elseif ($i + 5 < count($lines)) {
                            $l5 = trim($lines[$i+5] ?? '');
                            $code  = $l0;
                            $title = $l1;
                            $units = ctype_digit($l2) ? (int)$l2 : 0;
                            $lec   = ctype_digit($l3) ? (int)$l3 : 0;
                            $lab   = ctype_digit($l4) ? (int)$l4 : 0;
                            $prereq = $l5 !== '' ? $l5 : '-';
                            $skip   = 6;
                        } else {
                            $i++; continue;
                        }

                        if (!$code || !$title || strlen($code) < 2 || strlen($title) < 2) { $i++; continue; }

                        $group['Code'][]         = $code;
                        $group['Course_Title'][] = $title;
                        $group['units'][]        = $units;
                        $group['lec'][]          = $lec;
                        $group['lab'][]          = $lab;
                        $group['prerequisite'][] = $prereq;

                        $flatRows[] = [
                            'Code'         => $code,
                            'Course_Title' => $title,
                            'units'        => $units,
                            'lec'          => $lec,
                            'lab'          => $lab,
                            'prerequisite' => $prereq,
                            'year_level'   => $currentYear,
                            'semester'     => $currentSemester,
                            'track'        => $currentTrack,
                        ];

                        $i += $skip;
                        continue;
                    }

                    $i++;
                }
            }

            $flushGroup();
            file_put_contents($logPath,           $rawTextLog);
            file_put_contents($groupedResultPath, json_encode(['subjects' => $groupedSubjects], JSON_PRETTY_PRINT));
            file_put_contents($flatResultPath,    json_encode($flatRows, JSON_PRETTY_PRINT));

            if (empty($groupedSubjects)) {
                throw new \Exception('❌ No subjects found in parsed result.');
            }

            // Insert parsed subjects
            foreach ($groupedSubjects as $group) {
                $codes   = $group['Code'] ?? [];
                $titles  = $group['Course_Title'] ?? [];
                $units   = $group['units'] ?? [];
                $lecs    = $group['lec'] ?? [];
                $labs    = $group['lab'] ?? [];
                $prereqs = $group['prerequisite'] ?? [];

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

            DB::commit();

            if ($request->ajax()) {
                return response()->json(['success' => true, 'message' => 'Curriculum uploaded and parsed successfully.']);
            }
            return redirect()->route('programchair.curriculumupload')->with('imported', true);

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('❌ Upload Error: ' . $e->getMessage());
            if ($request->ajax()) {
                return response()->json(['success' => false, 'message' => 'Upload failed. ' . $e->getMessage()], 500);
            }
            return redirect()->route('programchair.curriculumupload')->with('error', 'Upload failed. ' . $e->getMessage());
        }
    }

    /** Inline view of the stored PDF. */
    public function view($curriculum_id)
    {
        $curriculum = Curriculum::findOrFail($curriculum_id);
        return response($curriculum->File_data)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="'.$curriculum->Curriculum_name.'"');
    }

    /** Delete a curriculum file/record. */
    public function destroy($curriculum_id)
    {
        $curriculum = Curriculum::findOrFail($curriculum_id);
        $curriculum->delete();

        if (request()->ajax()) {
            return response()->json(['success'=>true,'message'=>'Curriculum deleted.']);
        }
        return redirect()->route('programchair.curriculumupload')->with('deleted', true);
    }

    /** Delete a Curriculum AY (if no related curriculums exist). */
    public function destroyAy($id)
    {
        $ay = CurriculumAy::find($id);
        if (!$ay) {
            return response()->json(['success'=>false,'message'=>'Record not found.'], 404);
        }

        if (Curriculum::where('CurriculumAY_id', $id)->exists()) {
            return response()->json(['success'=>false,'message'=>'Cannot delete: there are curriculums linked to this AY.'], 409);
        }

        $ay->delete();
        return response()->json(['success'=>true]);
    }
}
