<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use setasign\Fpdi\Fpdi;
use App\Models\StudentManage;
use App\Models\Post;
use App\Models\PostRecipient;
use App\Models\Application;

class ApplicationController extends Controller
{
    /* ===================== PAGES ===================== */

    public function index()
    {
        $studentId = session('Student_id');
        if (!$studentId) abort(403, 'Student not logged in.');

        if (Application::where('Student_id', $studentId)->exists()) {
            return redirect()->route('student.application.status');
        }

        $student     = StudentManage::with('curriculum.curriculumAy.college')->findOrFail($studentId);
        $post        = Post::orderBy('Start_date', 'desc')->first();
        $collegeAbbr = optional($student->curriculum?->curriculumAy?->college)->Abbreviation ?? 'Your College';

        $unreadCount = PostRecipient::where('Student_id', $studentId)
            ->where('is_read', false)->count();

        $showPdf = session('showPdf', false);
        session()->forget('showPdf');

        return view('student.application', compact('showPdf', 'collegeAbbr', 'post', 'unreadCount'));
    }

    public function showApplication()
    {
        $studentId = session('Student_id');
        if (!$studentId) abort(403, 'Student not logged in.');

        if (Application::where('Student_id', $studentId)->exists()) {
            return redirect()->route('student.application.status');
        }

        $currentDate = now();
        $student = StudentManage::with('curriculum.curriculumAy.college')->findOrFail($studentId);
        $post    = Post::orderBy('Start_date', 'desc')->first();

        $isApplicationClosed = !($post && $currentDate->lessThanOrEqualTo($post->End_date));

        $unreadCount = PostRecipient::where('Student_id', $studentId)
            ->where('is_read', false)->count();

        $collegeAbbr = optional($student->curriculum?->curriculumAy?->college)->Abbreviation ?? 'Your College';

        return view('student.application', [
            'isApplicationClosed' => $isApplicationClosed,
            'student'             => $student,
            'post'                => $post,
            'unreadCount'         => $unreadCount,
            'collegeAbbr'         => $collegeAbbr,
        ]);
    }

    private function readLatestCogOutput(): string
    {
        $p = storage_path('app/cog/cog_output.txt');
        if (!is_file($p)) {
            throw new \RuntimeException('cog_output.txt not found.');
        }
        $txt = (string) @file_get_contents($p);
        if (trim($txt) === '') {
            throw new \RuntimeException('cog_output.txt is empty.');
        }
        return $txt;
    }

        public function validateGradeEligibility(Request $request)
    {
        $qrGrade = $request->input('qr_grade');
        
        // Check for valid grades (2.75, 3.00, INC, DROP)
        $validGrades = ['2.75', '3.00', 'INC', 'DROP'];
        if (!in_array($qrGrade, $validGrades)) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Grade is not eligible for application.',
            ], 422);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Grade is eligible for application.',
        ]);
    }

    public function validateGrades(Request $request)
{
    try {
        // Assuming the grades are passed as text content or the file paths
        $ocrGradesPath = storage_path('path_to/cog_ocr_output.txt');
        $outputGradesPath = storage_path('path_to/cog_output.txt');

        // Read the contents of both files
        $ocrGrades = file($ocrGradesPath, FILE_IGNORE_NEW_LINES);
        $outputGrades = file($outputGradesPath, FILE_IGNORE_NEW_LINES);

        // Compare the contents row by row
        $mismatches = $this->compareGrades($ocrGrades, $outputGrades);

        if (count($mismatches) > 0) {
            return response()->json(['status' => 'fail', 'mismatches' => $mismatches]);
        }

        return response()->json(['status' => 'success', 'message' => 'Grades validated successfully.']);
    } catch (\Exception $e) {
        return response()->json(['status' => 'error', 'message' => $e->getMessage()]);
    }
}

private function compareGrades($ocrGrades, $outputGrades)
{
    $mismatches = [];
    
    // Compare row by row
    foreach ($ocrGrades as $index => $ocrGrade) {
        // Assuming that each line contains the grade as the last element (adjust based on your file format)
        $ocrGradeData = explode(' ', trim($ocrGrade));
        $outputGradeData = explode(' ', trim($outputGrades[$index]));

        $ocrGradeValue = end($ocrGradeData);  // Get the last element (grade) from OCR file
        $outputGradeValue = end($outputGradeData);  // Get the last element (grade) from output file

        // Compare grades
        if ($ocrGradeValue !== $outputGradeValue) {
            $mismatches[] = [
                'index' => $index + 1,
                'ocr_grade' => $ocrGradeValue,
                'output_grade' => $outputGradeValue,
            ];
        }
    }

    return $mismatches;
}



    public function validateCogLatest()
    {
        try {
            $txt = $this->readLatestCogOutput();
            $result = $this->runCogValidationOnText($txt);
            $http = ($result['ok'] ?? false) ? 200 : 422;
            return response()->json($result, $http);
        } catch (\Throwable $e) {
            return response()->json(['ok'=>false,'reason'=>$e->getMessage()], 422);
        }
    }

    private function runCogValidationOnText(string $raw): array
    {
        // Normalize CR/NBSP/thin-space/BOM
        $norm = str_replace("\r", '', $raw);
        $norm = preg_replace("/\x{00A0}|\x{202F}|\x{FEFF}/u", ' ', $norm);

        // Super-tolerant delimiter: any =... OCR TEXT ...=
        if (!preg_match('/=+\s*OCR\s*TEXT\s*=+/iu', $norm, $m, PREG_OFFSET_CAPTURE)) {
            return ['ok' => false, 'reason' => 'Missing OCR section'];
        }

        $pos = $m[0][1];
        $len = strlen($m[0][0]);
        $qrBlock  = trim(substr($norm, 0, $pos));
        $ocrBlock = trim(substr($norm, $pos + $len));

        // Debugging - Check OCR Block Extraction
        Log::debug('OCR Block:', ['ocrBlock' => $ocrBlock]);

        // Clean up the OCR block (remove extraneous lines like timestamps, URLs, etc.)
        $ocrBlock = $this->cleanOcrText($ocrBlock);

        // Debug the cleaned OCR block
        Log::debug('Cleaned OCR Block:', ['ocrBlock' => $ocrBlock]);

        $qrRows  = $this->parseMarkdownTableRows($qrBlock);
        $ocrRows = $this->parseMarkdownTableRows($ocrBlock);
        if (!$ocrRows) $ocrRows = $this->parseFixedWidthOcrRows($ocrBlock);

        if (!$qrRows)  return ['ok'=>false,'reason'=>'No QR rows found'];
        if (!$ocrRows) return ['ok'=>false,'reason'=>'No OCR rows found'];

        $diff = $this->diffRowsByCode($qrRows, $ocrRows);

        return [
            'ok'         => empty($diff['mismatches']) && empty($diff['missing']) && empty($diff['extra']),
            'mismatches' => $diff['mismatches'],
            'missing'    => $diff['missing'],
            'extra'      => $diff['extra'],
        ];
    }

    private function cleanOcrText(string $ocrText): string
    {
        // Remove unnecessary sections:
        // 1. Remove timestamps (e.g., `6/3/24, 11:27 AM`)
        $ocrText = preg_replace('/\d{1,2}\/\d{1,2}\/\d{4}, \d{1,2}:\d{2} [APap]{2}/', '', $ocrText);

        // 2. Remove URLs
        $ocrText = preg_replace('/https?:\/\/[^\s]+/', '', $ocrText);

        // 3. Remove "NOTHING FOLLOWS" line
        $ocrText = preg_replace('/\*\* NOTHING FOLLOWS \*\*/', '', $ocrText);

        // Optional: Remove extra spaces or newlines that might remain
        $ocrText = preg_replace('/\s+/', ' ', $ocrText);

        return trim($ocrText);
    }


    /* ===================== FINAL SUBMIT ===================== */

    public function submitApplication(Request $request)
    {
        $studentId = session('Student_id');
        if (!$studentId) {
            return response()->json(['ok' => false, 'message' => 'Student not logged in.'], 403);
        }

        $data = $request->validate([
            'type'             => 'nullable|string|max:100',
            'file_name'        => 'required|string|max:255',
            'file_data_base64' => 'required|string',
            'gwa'              => 'nullable|numeric',
            'rank'             => 'nullable|string|max:100',
            'status'           => 'nullable|string|max:50',
            'context'          => 'nullable',
        ]);

        $b64 = $data['file_data_base64'];
        if (str_starts_with($b64, 'data:')) {
            $comma = strpos($b64, ',');
            $b64   = $comma !== false ? substr($b64, $comma + 1) : $b64;
        }
        $binary = base64_decode($b64, true);
        if ($binary === false || $binary === null) {
            return response()->json(['ok' => false, 'message' => 'Invalid file_data_base64.'], 422);
        }
        if (strlen($binary) > 10 * 1024 * 1024) {
            return response()->json(['ok' => false, 'message' => 'PDF is too large. Max 10MB.'], 413);
        }

        $type   = $data['type']   ?? 'DeanLister';
        $status = $data['status'] ?? 'Pending';

        $gwaInput = $data['gwa'] ?? null;
        $gwaNum   = is_numeric($gwaInput) ? (float)$gwaInput : null;
        $rankIn   = $data['rank'] ?? null;

        if ($gwaNum === null && $request->has('context')) {
            try {
                $ctx = $request->input('context');
                $ctxGwa = data_get($ctx, 'totals.gwa');
                if (is_numeric($ctxGwa)) $gwaNum = (float) $ctxGwa;
            } catch (\Throwable $e) { /* ignore */ }
        }

        $rankFinal = $rankIn ?: ($this->rankFromGwaNullable($gwaNum) ?? 'Unranked');

        $gwaToStore  = $gwaNum ?? 0.0;
        $rankToStore = $rankFinal ?: 'Unranked';

        try {
            $app = new Application();
            $app->Student_id = $studentId;
            $app->Type       = $type;
            $app->File_name  = $data['file_name'];
            $app->File_data  = $binary;
            $app->GWA        = $gwaToStore;
            $app->Rank       = $rankToStore;
            $app->Status     = $status ?: 'Pending';
            $app->save();

            try {
                if ($request->has('context')) {
                    $logDir  = storage_path('app/application_logs/'.$studentId);
                    File::ensureDirectoryExists($logDir);
                    $fname = 'app_'.$app->Application_id.'_'.now()->format('Ymd_His').'.json';
                    File::put($logDir.DIRECTORY_SEPARATOR.$fname, json_encode([
                        'Student_id'  => $studentId,
                        'Application' => [
                            'Application_id' => $app->Application_id,
                            'Type'           => $type,
                            'File_name'      => $data['file_name'],
                            'GWA'            => $gwaToStore,
                            'Rank'           => $rankToStore,
                            'Status'         => $app->Status,
                        ],
                        'context' => $request->input('context'),
                    ], JSON_PRETTY_PRINT));
                }
            } catch (\Throwable $logErr) {
                Log::warning('Application audit log failed', ['err' => $logErr->getMessage()]);
            }

            return response()->json([
                'ok'             => true,
                'application_id' => $app->Application_id,
                'status'         => $app->Status,
                'rank'           => $app->Rank,
                'message'        => 'Application submitted.',
            ], 201);
        } catch (\Throwable $e) {
            Log::error('submitApplication failed', [
                'err'  => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return response()->json(['ok' => false, 'message' => 'Unable to save application.'], 500);
        }
    }

    private function rankFromGwaNullable(?float $gwa): ?string
    {
        if (!is_numeric($gwa)) return null;
        if ($gwa >= 1.0000 && $gwa <= 1.2500) return 'Tech Savant';
        if ($gwa <= 1.5000)                   return 'Tech Virtuoso';
        if ($gwa <= 1.7500)                   return 'Tech Prodigy';
        return null;
    }

    /* ===================== UPLOADS ===================== */

    public function uploadCor(Request $request)
    {
        $request->validate(['pdf' => 'required|file|mimes:pdf|max:25600']);

        $dir = storage_path('app/cor');
        File::ensureDirectoryExists($dir);

        $this->purgeOldCorFiles();

        $fixedPdf = $dir.DIRECTORY_SEPARATOR.'cor_upload.pdf';
        $request->file('pdf')->move($dir, 'cor_upload.pdf');

        $fixedPng = $dir.DIRECTORY_SEPARATOR.'cor_upload.png';
        $this->renderPdfPage1ToPng($fixedPdf, $fixedPng);

        $pubPdfRel = 'uploads/cor/cor_upload.pdf';
        $pubPngRel = 'uploads/cor/cor_upload.png';
        $pubPdfAbs = storage_path('app/public/'.$pubPdfRel);
        $pubPngAbs = storage_path('app/public/'.$pubPngRel);
        File::ensureDirectoryExists(dirname($pubPdfAbs));
        @copy($fixedPdf, $pubPdfAbs);
        if (is_file($fixedPng)) @copy($fixedPng, $pubPngAbs);

        return response()->json([
            'ok'              => true,
            'message'         => 'Saved as cor_upload.pdf',
            'cor_pdf_path'    => $fixedPdf,
            'cor_pdf_url'     => asset('storage/'.$pubPdfRel),
            'cor_png_path'    => is_file($fixedPng) ? $fixedPng : null,
            'cor_png_url'     => is_file($fixedPng) ? asset('storage/'.$pubPngRel) : null,
        ]);
    }

    public function uploadCog(Request $request)
    {
        @set_time_limit(300);

        $hasPdf   = $request->hasFile('pdf');
        $hasImage = $request->hasFile('image');

        if (!$hasPdf && !$hasImage) {
            return response()->json(['ok'=>false,'error'=>'No file uploaded. Use field "pdf" or "image".'], 422);
        }

        if ($hasPdf) {
            $request->validate(['pdf' => 'required|file|mimes:pdf|max:25600']);
        } else {
            $request->validate(['image' => 'required|file|mimes:png,jpg,jpeg|max:25600']);
        }


        $dir = storage_path('app/cog');
        File::ensureDirectoryExists($dir);

        $finalPng = $dir.DIRECTORY_SEPARATOR.'cog_upload.png';
        $finalPdf = $dir.DIRECTORY_SEPARATOR.'cog_upload.pdf';
        $ocrText  = '';
        $pngMade  = false;

        try {
            if ($hasPdf) {
                @unlink($finalPdf);
                @unlink($finalPng);
                $request->file('pdf')->move($dir, 'cog_upload.pdf');

                // 1) Extract raw text (for OCR section)
                $ocrText = $this->pdfToText($finalPdf);

                // 2) Render a PNG (best effort)
                $pngMade = $this->renderPdfPage1ToPng($finalPdf, $finalPng);
                if ($pngMade) {
                    $this->cogTopContentCrop($finalPng, $finalPng);
                } else {
                    Log::warning('COG: PNG not created from PDF (continuing gracefully).');
                }

            } else {
                // Image branch
                $tmp = $request->file('image')->move($dir, 'cog_upload.tmp');
                try {
                    $this->forceToPng((string)$tmp, $finalPng);
                    $pngMade = true;
                } finally {
                    @unlink((string)$tmp);
                }
                $this->trimWhiteMargins($finalPng);
                $this->cogTopContentCrop($finalPng, $finalPng);
            }

            // Publish preview PNG if we have one
            $publicUrl = null;
            if ($pngMade && is_file($finalPng)) {
                $pubRel = 'uploads/cog/cog_upload.png';
                $pubAbs = storage_path('app/public/'.$pubRel);
                File::ensureDirectoryExists(dirname($pubAbs));
                @copy($finalPng, $pubAbs);
                $publicUrl = asset('storage/'.$pubRel);
            }

            return response()->json([
                'ok'             => true,
                'message'        => $pngMade ? 'COG uploaded successfully.' : 'COG uploaded (local preview will be used).',
                'cog_image_path' => $pngMade ? $finalPng : null,
                'cog_image_url'  => $publicUrl,
                'public_url'     => $publicUrl,
                'ocr_text'       => $ocrText,
                'pdf_path'       => is_file($finalPdf) ? $finalPdf : null,
            ]);
        } catch (\Throwable $e) {
            Log::error('COG upload fail', ['err'=>$e->getMessage()]);
            return response()->json([
                'ok'        => true,
                'message'   => 'COG accepted with warnings; using local preview.',
                'cog_image_path' => null,
                'cog_image_url'  => null,
                'public_url'     => null,
                'ocr_text'       => '',
                'pdf_path'       => is_file($finalPdf) ? $finalPdf : null,
                'warn'           => $e->getMessage(),
            ]);
        }
    }


    public function uploadAttachments(Request $request)
    {
        $request->validate([
            'cor' => ['required','file','mimes:pdf','max:25600'],
            'cog' => ['required','file','mimes:png,jpg,jpeg','max:25600'],
        ]);

        $dirCor = storage_path('app/cor');
        File::ensureDirectoryExists($dirCor);
        $this->purgeOldCorFiles();
        $fixedPdf = $dirCor.DIRECTORY_SEPARATOR.'cor_upload.pdf';
        $request->file('cor')->move($dirCor, 'cor_upload.pdf');
        $fixedPng = $dirCor.DIRECTORY_SEPARATOR.'cor_upload.png';
        $this->renderPdfPage1ToPng($fixedPdf, $fixedPng);

        $dirCog = storage_path('app/cog');
        File::ensureDirectoryExists($dirCog);
        $pngAbs = $dirCog.DIRECTORY_SEPARATOR.'cog_upload.png';
        $tmp    = $request->file('cog')->move($dirCog, 'cog_upload.tmp');
        try { $this->forceToPng((string)$tmp, $pngAbs); } finally { @unlink((string)$tmp); }

        File::ensureDirectoryExists(storage_path('app/public/uploads/cor'));
        File::ensureDirectoryExists(storage_path('app/public/uploads/cog'));
        @copy($fixedPdf, storage_path('app/public/uploads/cor/cor_upload.pdf'));
        if (is_file($fixedPng)) @copy($fixedPng, storage_path('app/public/uploads/cor/cor_upload.png'));
        @copy($pngAbs, storage_path('app/public/uploads/cog/cog_upload.png'));

        return response()->json(['success' => true]);
    }

    /* ===================== TEXT SAVERS ===================== */

    public function saveCorOutput(Request $request)
    {
        $text = (string) ($request->input('raw_text', '') ?: $request->input('text', ''));
        if ($text === '') return response()->json(['success'=>false,'message'=>'No text provided.'],422);
        try {
            $path = storage_path('app/cor/cor_output.txt');
            File::ensureDirectoryExists(dirname($path));
            File::put($path, trim($text).PHP_EOL);
            return response()->json(['success'=>true,'path'=>$path]);
        } catch (\Throwable $e) {
            Log::error('saveCorOutput failed', ['err'=>$e->getMessage()]);
            return response()->json(['success'=>false,'message'=>$e->getMessage()],500);
        }
    }

    public function saveCogDebug(Request $request)
    {
        try {
            $path = storage_path('app/cog/debug_raw_text.txt');
            File::ensureDirectoryExists(dirname($path));
            $payload = "====================== DEBUG ======================\n[TIME] ".now()->format('Y-m-d H:i:s')."\n"
                .trim((string)$request->input('text',''))."\n===================================================\n\n";
            file_put_contents($path, $payload, FILE_APPEND);
            return response()->json(['success'=>true,'path'=>$path]);
        } catch (\Throwable $e) {
            Log::error('saveCogDebug failed', ['err'=>$e->getMessage()]);
            return response()->json(['success'=>false,'message'=>$e->getMessage()],500);
        }
    }

    /**
     * Writes cog_output.txt exactly like your sample.
     */
public function saveCogOutput(Request $request)
{
    $dir  = storage_path('app/cog');
    File::ensureDirectoryExists($dir);

    // Data from the request
    $meta    = (array) ($request->input('meta')   ?? []);
    $rows    = (array) ($request->input('rows')   ?? []);
    $totals  = (array) ($request->input('totals') ?? []);
    $qrRaw   = (string) $request->input('qr_raw', '');
    $ocrFull = (string) $request->input('ocr_full', '');
    $ocrJson = (string) $request->input('ocr_text', '');
    $pdfTxt  = (string) $request->input('pdf_text', '');

    $finalOcr = trim($pdfTxt) ?: trim($ocrJson) ?: trim($ocrFull) ?: '';
    if ($finalOcr === '') {
        $maybePdf = storage_path('app/cog/cog_upload.pdf');
        if (is_file($maybePdf)) $finalOcr = $this->pdfToText($maybePdf);
    }

    $header = (array) $request->input('header', []);
    if (!empty($header)) $meta = $this->mapHeaderToMeta($header, $meta);

    // Render QR in pretty format with header and totals
    $prettyQr = $this->renderCogPrettyWithHeaderTableTotals($meta, $rows, $totals);

    $timestamp = now()->format('Y-m-d H:i:s');

    // Save QR data to `cog_qr_output.txt`
    $qrOutput = "[TIME] {$timestamp}\n[SOURCE:QR]\n{$qrRaw}\n";
    $qrOutput .= str_repeat('=', 51)."\n\n";
    $qrOutput .= $prettyQr . "\n";
    
    // Save OCR data to `cog_ocr_output.txt`
    $ocrOutput = "===============OCR TEXT===============\n\n";
    $ocrOutput .= trim($finalOcr)."\n";

    try {
        // Save QR to a file
        $qrPath = $dir . DIRECTORY_SEPARATOR . 'cog_output.txt';
        file_put_contents($qrPath, $qrOutput, LOCK_EX);

        // Save OCR to a separate file
        $ocrPath = $dir . DIRECTORY_SEPARATOR . 'cog_ocr_output.txt';
        file_put_contents($ocrPath, $ocrOutput, LOCK_EX);

        return response()->json([
            'ok'         => true,
            'qr_path'    => $qrPath,
            'ocr_path'   => $ocrPath,
            'mode'       => 'qr+ocr_separated',
        ]);
    } catch (\Throwable $e) {
        Log::error('saveCogOutput failed', ['err'=>$e->getMessage()]);
        return response()->json(['success'=>false,'message'=>$e->getMessage()],500);
    }
}


    public function logJsError(Request $request)
    {
        Log::error('JS Client Error', [
            'student_id' => session('Student_id'),
            'type'       => $request->input('type'),
            'message'    => $request->input('message'),
            'stack'      => $request->input('stack'),
            'step'       => $request->input('step'),
            'url'        => $this->safeReferer($request),
            'ua'         => $request->userAgent(),
        ]);
        return response()->json(['ok'=>true]);
    }

    private function safeReferer(Request $r): string
    {
        try { return (string) $r->header('referer'); } catch (\Throwable $e) { return ''; }
    }

    /* ===================== JSON-DRIVEN GENERATOR ===================== */
    

    public function generateDeanListFormFromJson(Request $request)
    {
        $data = $request->validate([
            'template_path' => 'nullable|string',
            'meta'          => 'required|array',
            'rows'          => 'required|array|min:1',
            'totals'        => 'nullable|array',
            'cor_png_path'  => 'nullable|string',
            'cog_png_path'  => 'nullable|string',
        ]);

        $tplFromClient = $data['template_path'] ?? null;
        if ($tplFromClient && is_file($tplFromClient)) {
            $template = $tplFromClient;
        } else {
            $template = storage_path("app/pdf_templates/Dean's List Application Form CLEAN.pdf");
            if (!is_file($template)) {
                $maybe2025 = storage_path("app/pdf_templates/Dean's List Application Form 2025.pdf");
                if (is_file($maybe2025)) $template = $maybe2025;
            }
        }
        if (!is_file($template)) {
            return response()->json([
                'error'   => 'Template not found',
                'message' => 'Put the PDF in storage/app/pdf_templates/',
            ], 404);
        }

        $corPdf      = storage_path('app/cor/cor_upload.pdf');
        $corPngFixed = $data['cor_png_path'] ?? storage_path('app/cor/cor_upload.png');
        $cogPngFixed = $data['cog_png_path'] ?? storage_path('app/cog/cog_upload.png');

        if (!is_file($corPngFixed) && is_file($corPdf)) {
            $this->renderPdfPage1ToPng($corPdf, $corPngFixed);
        }

        $corPng = is_file($corPngFixed) ? str_replace('\\','/',$corPngFixed) : null;
        $cogPng = is_file($cogPngFixed) ? str_replace('\\','/',$cogPngFixed) : null;

        $meta   = (array)($data['meta'] ?? []);
        $rowsIn = (array)($data['rows'] ?? []);
        $totals = (array)($data['totals'] ?? []);

        $courseRows = [];
        foreach ($rowsIn as $r) {
            $code  = (string)($r['code']  ?? '');
            $title = (string)($r['title'] ?? '');
            $units = (float) ($r['units'] ?? 0);
            $gradeVal = $r['grade'] ?? 0;
            $grade = is_numeric($gradeVal) ? (float)$gradeVal : (float)preg_replace('/[^\d.]+/','',(string)$gradeVal);
            $wg    = ($units > 0 && $grade > 0) ? $units * $grade : 0.0;

            $courseRows[] = [
                'name'  => trim($code.' '.$title),
                'grade' => $grade,
                'units' => $units,
                'wg'    => $wg,
            ];
        }

        $totalUnits = $totals['total_units'] ?? null;
        if ($totalUnits === null) {
            $totalUnits = 0.0;
            foreach ($courseRows as $r) $totalUnits += (float)$r['units'];
        }
        $totalWG = 0.0;
        foreach ($courseRows as $r) $totalWG += (float)$r['wg'];

        $gwa = $totals['gwa'] ?? null;
        if ($gwa === null) $gwa = $totalUnits > 0 ? round($totalWG / $totalUnits, 4) : '';

        $rank = '';
        if (is_numeric($gwa)) {
            $g = (float)$gwa;
            if     ($g >= 1.0000 && $g <= 1.2500) $rank = 'Tech Savant';
            elseif ($g <= 1.5000)                 $rank = 'Tech Virtuoso';
            elseif ($g <= 1.7500)                 $rank = 'Tech Prodigy';
        }

        $studentId = session('Student_id');
        if (!$studentId) return response()->json(['error' => 'Student not logged in.'], 403);

        $student = StudentManage::with([
            'curriculum.curriculumAy.college',
            'curriculum.curriculumAy.major',
            'curriculum.curriculumAy.program',
            'curriculum.curriculumAy.campus',
        ])->findOrFail($studentId);

        $first  = strtoupper($student->First_name ?? '');
        $middle = strtoupper($student->Middle_name ?? '');
        $last   = strtoupper($student->Last_name ?? '');
        $middleInitial = $middle ? strtoupper(substr($middle, 0, 1)).'.' : '';
        $Contact = (string)($student->Contact ?? '');

        $curriculumAy = $student->curriculum?->curriculumAy;
        $college      = (string)($curriculumAy?->college?->College_name ?? '');
        $collegeAbbr  = strtoupper($curriculumAy?->college?->Abbreviation ?? '');
        $programName  = strtoupper($curriculumAy?->program?->Program_name ?? '');
        $majorName    = strtoupper($curriculumAy?->major?->Major_name ?? '');

        $semester      = (string)($meta['semester']      ?? '');
        $academicYear  = (string)($meta['academic_year'] ?? '');
        $yearLevel     = (string)($meta['year_level']    ?? '');
        $section       = (string)($meta['section']       ?? '');
        $scholarship   = (string)($meta['scholarship']   ?? '');
        $course        = (string)($meta['program']       ?? $programName);
        $track         = (string)($meta['track']         ?? $majorName);

        $relOut = 'generated/deanslist_'.now()->format('Ymd_His').'_'.Str::random(5).'.pdf';
        $absOut = storage_path('app/public/'.$relOut);
        File::ensureDirectoryExists(dirname($absOut));

        try {
            $pdf = new Fpdi();
            $pageCount = $pdf->setSourceFile($template);

            $tpl1  = $pdf->importPage(1);
            $size1 = $pdf->getTemplateSize($tpl1);
            $pdf->AddPage($size1['orientation'], [$size1['width'], $size1['height']]);
            $pdf->useTemplate($tpl1);

            $pdf->SetFont('Times', 'B', 12);
            $pdf->SetXY(25, 61.5);
            $pdf->Cell(200, 10, $college, 0, 0, '');

            $pdf->SetFont('Times', '', 12);
            $pdf->SetXY(85, 185);
            $pdf->Write(0, "$semester Semester, AY $academicYear");

            $pdf->SetFont('Times', 'B', 12);
            $pdf->SetXY(57.5, 196);  $pdf->Write(0, $last);
            $pdf->SetXY(102 , 196);  $pdf->Write(0, $first);
            $pdf->SetXY(166 , 196);  $pdf->Write(0, $middleInitial);

            $pdf->SetFont('Times', 'U', 12);
            $pdf->SetXY(55  , 205.5); $pdf->Write(0, $Contact);
            $pdf->SetXY(39.5, 210  ); $pdf->Write(0, $course);
            $pdf->SetXY(41  , 215  ); $pdf->Write(0, $yearLevel);
            $pdf->SetXY(103 , 215  ); $pdf->Write(0, $track);
            $pdf->SetXY(47  , 220  ); $pdf->Write(0, $scholarship);

            $pdf->SetFont('Times', '', 12);
            $yStart = 240;
            foreach ($courseRows as $i => $row) {
                $y = $yStart + ($i * 5.3);
                $pdf->SetXY(30  , $y);   $pdf->Write(0, (string)$row['name']);
                $pdf->SetXY(133 , $y);   $pdf->Write(0, number_format((float)$row['grade'], 2));
                $pdf->SetXY(154.5, $y);  $pdf->Write(0, (string)$row['units']);
                $pdf->SetXY(169.8, $y);  $pdf->Write(0, number_format((float)$row['wg'], 2));
            }

            $pdf->SetXY(154.5, 286.5); $pdf->Write(0, $totalUnits);
            $pdf->SetXY(169.8, 286.5); $pdf->Write(0, number_format($totalWG, 2));
            if ($gwa !== '') {
                $pdf->SetXY(166, 291.5); $pdf->Write(0, is_numeric($gwa) ? number_format((float)$gwa, 4) : (string)$gwa);
            }
            $pdf->SetXY(160, 296.5);   $pdf->Write(0, $rank);

            if ($pageCount >= 2) {
                $tpl2  = $pdf->importPage(2);
                $size2 = $pdf->getTemplateSize($tpl2);
                $pdf->AddPage($size2['orientation'], [$size2['width'], $size2['height']]);
                $pdf->useTemplate($tpl2);

                $COR_BOX = ['x'=>26.0, 'y'=>18.0,  'w'=>167.0, 'h'=>118.0];
                $COG_BOX = ['x'=>25.0, 'y'=>200.0, 'w'=>170.0, 'h'=>118.0];
                $BLEED   = 1.5;

                $COR_BIAS_Y  = max(-1, min(1, (float)($meta['cor_bias_y'] ?? -0.70)));
                $COG_BIAS_Y  = max(-1, min(1, (float)($meta['cog_bias_y'] ?? -0.30)));
                $COR_SHIFT_Y = (float)($meta['cor_shift_y'] ?? -15.0);
                $COG_SHIFT_Y = (float)($meta['cog_shift_y'] ?? -89.0);

                $COR_BOX['y'] += $COR_SHIFT_Y;
                $COG_BOX['y'] += $COG_SHIFT_Y;

                if ($corPng) {
                    $tmp = $this->makeCoverFitTemp($corPng, $COR_BOX['w'], $COR_BOX['h'], $COR_BIAS_Y);
                    if ($tmp) {
                        $pdf->Image($tmp, $COR_BOX['x'] - $BLEED/2, $COR_BOX['y'] - $BLEED/2, $COR_BOX['w'] + $BLEED, $COR_BOX['h'] + $BLEED);
                        @unlink($tmp);
                    } else {
                        $this->placeContain($pdf, $corPng, $COR_BOX);
                    }
                }
                if ($cogPng) {
                    $tmp = $this->makeCoverFitTemp($cogPng, $COG_BOX['w'], $COG_BOX['h'], $COG_BIAS_Y);
                    if ($tmp) {
                        $pdf->Image($tmp, $COG_BOX['x'] - $BLEED/2, $COG_BOX['y'] - $BLEED/2, $COG_BOX['w'] + $BLEED, $COG_BOX['h'] + $BLEED);
                        @unlink($tmp);
                    } else {
                        $this->placeContain($pdf, $cogPng, $COG_BOX);
                    }
                }
            }

            $pdf->Output($absOut, 'F');

            return response()->json([
                'ok'         => true,
                'path'       => $absOut,
                'public_url' => asset('storage/'.$relOut),
            ]);
        } catch (\Throwable $e) {
            Log::error('PDF generation failed', ['err'=>$e->getMessage()]);
            return response()->json(['error'=>'PDF build failed','message'=>$e->getMessage()],500);
        }
    }

    public function regenerateDeanListForm() { abort(410, 'Legacy generator disabled. Use generateDeanListFormFromJson.'); }
    public function generateDeanListForm()   { abort(410, 'Legacy generator disabled. Use generateDeanListFormFromJson.'); }

    /* ===================== QR RESOLUTION ===================== */

    public function resolveQr(Request $request)
    {
        $request->validate(['payload' => 'required|string']);
        $payload = trim($request->string('payload'));

        if (preg_match('/^https?:\/\//i', $payload)) {
            $url  = $payload;
            $host = parse_url($url, PHP_URL_HOST) ?? '';
            if (!in_array($host, ['dione.batstate-u.edu.ph'], true)) {
                return response()->json(['error'=>'Host not allowed'],403);
            }
            $res = Http::timeout(20)->get($url);
            if (!$res->ok()) return response()->json(['error'=>'Unable to fetch QR page'],422);
            [$header,$grades,$totUnits,$gwa] = $this->parseGradesHtml($res->body());
            if (!$grades) return response()->json(['error'=>'No grades table found'],422);
            return response()->json([
                'header'=>$header,'grades'=>$grades,'total_units'=>$totUnits,'gwa'=>$gwa,
            ]);
        }

        if (preg_match('/^[A-Za-z0-9\-_]+\.[A-Za-z0-9\-_]+\.[A-Za-z0-9\-_]+$/', $payload)) {
            $parts = explode('.', $payload);
            $json  = $this->b64u($parts[1] ?? '');
            $obj   = json_decode($json, true);
            if (is_array($obj) && !empty($obj['grades'])) {
                return response()->json([
                    'header'=>$obj['header'] ?? [], 'grades'=>$obj['grades'],
                    'total_units'=>$obj['total_units'] ?? null, 'gwa'=>$obj['gwa'] ?? null,
                ]);
            }
            return response()->json(['error'=>'JWT has no grades'],422);
        }

        return response()->json(['error'=>'Unsupported QR payload'],422);
    }

    private function b64u(string $s): string
    {
        $s = strtr($s, '-_', '+/');
        $pad = strlen($s) % 4;
        if ($pad) $s .= str_repeat('=', 4 - $pad);
        return base64_decode($s) ?: '';
    }

    private function parseGradesHtml(string $html): array
    {
        libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->loadHTML($html);
        $xp  = new \DOMXPath($dom);

        $getText = function(string $label) use ($xp): string {
            foreach ($xp->query('//text()') as $t) {
                $s = trim($t->nodeValue);
                if (stripos($s, $label) === 0) {
                    return trim(preg_replace('/^'.preg_quote($label,'/').'\s*:\s*/i','',$s));
                }
            }
            return '';
        };

        $header = [
            'Fullname'      => $getText('Fullname'),
            'SRCODE'        => $getText('SRCODE'),
            'College'       => $getText('College'),
            'Academic Year' => $getText('Academic Year'),
            'Program'       => $getText('Program'),
            'Semester'      => $getText('Semester'),
            'Year Level'    => $getText('Year Level'),
        ];

        $grades = [];
        foreach ($xp->query('//table//tr') as $tr) {
            $cells = [];
            foreach ($tr->childNodes as $c) {
                if ($c instanceof \DOMElement && in_array(strtolower($c->nodeName), ['td','th'], true)) {
                    $cells[] = trim(preg_replace('/\s+/', ' ', $c->textContent));
                }
            }
            if (count($cells) >= 7 && is_numeric($cells[0])) {
                $grades[] = [
                    'name'       => trim($cells[1].' '.$cells[2]),
                    'units'      => (float)$cells[3],
                    'grade'      => $cells[4],
                    'section'    => $cells[5],
                    'instructor' => $cells[6],
                ];
            }
        }

        $totUnits = 0; $sumUxG = 0.0;
        foreach ($grades as $g) {
            $u = (float)($g['units'] ?? 0);
            $totUnits += $u;
            $gr = (float)($g['grade'] ?? 0);
            if ($u > 0 && $gr > 0) $sumUxG += $u * $gr;
        }
        $gwa = $totUnits ? round($sumUxG / $totUnits, 4) : null;

        return [$header, $grades, $totUnits, $gwa];
    }

    /* ===================== IMAGE / PROCESS HELPERS ===================== */

    private function purgeOldCorFiles(): void
    {
        $dir = storage_path('app/cor');
        try {
            foreach (glob($dir.DIRECTORY_SEPARATOR.'cor_*.pdf') ?: [] as $f) @unlink($f);
            @unlink($dir.DIRECTORY_SEPARATOR.'cor_upload.pdf');
            @unlink($dir.DIRECTORY_SEPARATOR.'cor_upload.png');
        } catch (\Throwable $e) {
            Log::warning('purgeOldCorFiles failed', ['err'=>$e->getMessage()]);
        }
    }

    private function imageSize(string $path): array
    {
        try {
            $info = @getimagesize($path);
            $pxW = $info[0] ?? 0; $pxH = $info[1] ?? 0;

            $dpi = 96.0;
            if (function_exists('exif_read_data')) {
                $exif = @exif_read_data($path);
                if ($exif && isset($exif['XResolution'])) {
                    $x = (float)$exif['XResolution'];
                    $unit = $exif['ResolutionUnit'] ?? 2;
                    if ($x > 0) $dpi = ($unit == 3) ? $x * 2.54 : $x;
                }
            }
            $mmPerIn = 25.4;
            return [($pxW / max(1.0, $dpi)) * $mmPerIn, ($pxH / max(1.0, $dpi)) * $mmPerIn];
        } catch (\Throwable $e) {
            return [0,0];
        }
    }

    private function makeCoverFitTemp(string $srcPath, float $boxWmm, float $boxHmm, float $biasY = 0.0, float $biasX = 0.0): ?string
    {
        if (!extension_loaded('imagick')) return null;
        try {
            $im = new \Imagick();
            $im->readImage($srcPath);
            if ($im->getNumberImages() > 1) $im = $im->coalesceImages();
            $im->setImageColorspace(\Imagick::COLORSPACE_RGB);
            $im->setImageAlphaChannel(\Imagick::ALPHACHANNEL_REMOVE);
            $im->mergeImageLayers(\Imagick::LAYERMETHOD_FLATTEN);

            $w = $im->getImageWidth(); $h = $im->getImageHeight();
            $targetAR = $boxWmm / max(0.0001, $boxHmm);
            $imgAR    = $w / max(1, $h);

            if ($imgAR > $targetAR) {
                $cropW = (int) floor($h * $targetAR);
                $cropH = $h;
                $x = (int) round((($w - $cropW) * (1 + $biasX)) / 2);
                $x = max(0, min($x, $w - $cropW));
                $y = 0;
            } else {
                $cropW = $w;
                $cropH = (int) floor($w / $targetAR);
                $y = (int) round((($h - $cropH) * (1 + $biasY)) / 2);
                $y = max(0, min($y, $h - $cropH));
                $x = 0;
            }

            $im->cropImage($cropW, $cropH, $x, $y);
            $im->setImagePage(0,0,0,0);
            if ($im->getImageWidth() > 2200) $im->thumbnailImage(2200, 0);

            $tmp = sys_get_temp_dir().DIRECTORY_SEPARATOR.'cover_'.uniqid().'.png';
            $im->setImageFormat('png');
            $im->writeImage($tmp);
            $im->clear(); $im->destroy();

            return $tmp;
        } catch (\Throwable $e) {
            Log::warning('makeCoverFitTemp failed', ['err'=>$e->getMessage(), 'src'=>$srcPath]);
            return null;
        }
    }

    private function placeContain(Fpdi $pdf, string $imgPath, array $box): void
    {
        [$imgWmm, $imgHmm] = $this->imageSize($imgPath);
        if (!$imgWmm || !$imgHmm) return;

        $ratio = min($box['w'] / $imgWmm, $box['h'] / $imgHmm);
        $w = $imgWmm * $ratio; $h = $imgHmm * $ratio;
        $x = $box['x'] + ($box['w'] - $w) / 2;
        $y = $box['y'] + ($box['h'] - $h) / 2;

        $pdf->Image($imgPath, $x, $y, $w, $h);
    }

    private function forceToPng(string $srcAbs, string $dstAbs): void
    {
        $ext = strtolower(pathinfo($srcAbs, PATHINFO_EXTENSION));
        if ($ext === 'png') { @copy($srcAbs, $dstAbs); return; }
        if (!extension_loaded('imagick')) { @copy($srcAbs, $dstAbs); return; }

        try {
            $im = new \Imagick($srcAbs);
            $im->setImageColorspace(\Imagick::COLORSPACE_RGB);
            $im->setImageAlphaChannel(\Imagick::ALPHACHANNEL_REMOVE);
            $im->mergeImageLayers(\Imagick::LAYERMETHOD_FLATTEN);
            $im->setImageFormat('png');
            $im->writeImage($dstAbs);
            $im->clear(); $im->destroy();
        } catch (\Throwable $e) {
            Log::warning('forceToPng failed', ['err'=>$e->getMessage(), 'src'=>$srcAbs, 'dst'=>$dstAbs]);
            @copy($srcAbs, $dstAbs);
        }
    }

    private function renderPdfPage1ToPng(string $pdfAbs, string $outPngAbs): bool
    {
        $ok = false;

        $bin = $this->findBinaryFromEnv('POPPLER_PATH', 'pdftoppm');
        if ($bin) {
            try {
                $noExt = $outPngAbs.'.page1';
                $cmd   = [$bin, '-png', '-singlefile', '-r', '200', $pdfAbs, $noExt];
                $this->run($cmd, 90);
                $cand = $noExt.'.png';
                if (is_file($cand)) {
                    @rename($cand, $outPngAbs);
                    $ok = true;
                }
            } catch (\Throwable $e) {
                Log::warning('pdftoppm failed', ['err'=>$e->getMessage()]);
            }
        }

        if (!$ok && extension_loaded('imagick')) {
            try {
                $im = new \Imagick();
                $im->setResolution(200,200);
                $im->readImage($pdfAbs.'[0]');
                $im->setImageBackgroundColor('white');
                $im->setImageAlphaChannel(\Imagick::ALPHACHANNEL_REMOVE);
                $im = $im->mergeImageLayers(\Imagick::LAYERMETHOD_FLATTEN);
                $im->setImageFormat('png');
                $im->writeImage($outPngAbs);
                $im->clear(); $im->destroy();
                $ok = is_file($outPngAbs);
            } catch (\Throwable $e) {
                Log::warning('Imagick render failed', ['err'=>$e->getMessage()]);
            }
        }

        if ($ok) $this->trimWhiteMargins($outPngAbs);
        return $ok;
    }

    private function trimWhiteMargins(string $pngPath): void
    {
        if (!is_file($pngPath)) return;

        $enable = filter_var(env('COG_ENABLE_TRIM', false), FILTER_VALIDATE_BOOL);
        if (!$enable) return;

        if (!extension_loaded('imagick')) {
            \Log::warning('trimWhiteMargins skipped (Imagick not loaded)', ['file' => $pngPath]);
            return;
        }

        try {
            $fuzz = (float) env('COG_TRIM_FUZZ', 2.0);
            $im = new \Imagick($pngPath);
            $im->setImageBackgroundColor('white');
            $im->setImageAlphaChannel(\Imagick::ALPHACHANNEL_REMOVE);
            $im->setImageColorspace(\Imagick::COLORSPACE_RGB);

            $im->trimImage(max(0, (int)round($fuzz)));
            $im->setImagePage(0, 0, 0, 0);
            $im->writeImage($pngPath);
            $im->clear(); $im->destroy();
        } catch (\Throwable $e) {
            \Log::warning('trimWhiteMargins failed', ['err' => $e->getMessage()]);
        }
    }

    private function cogTopContentCrop(string $srcPng, string $dstPng): void
    {
        if (!is_file($srcPng)) return;
        if (!extension_loaded('imagick')) return;

        $topFrom  = (float) env('COG_TOP_FROM', 0.05);
        $topTo    = (float) env('COG_TOP_TO',   0.90);
        $mLeft    = (float) env('COG_MARGIN_LEFT',  0.00);
        $mRight   = (float) env('COG_MARGIN_RIGHT', 0.00);

        $topFrom = max(0.00, min(0.45, $topFrom));
        $topTo   = max($topFrom + 0.30, min(0.98, $topTo));
        $mLeft   = max(0.00, min(0.25, $mLeft));
        $mRight  = max(0.00, min(0.25, $mRight));

        try {
            $im = new \Imagick($srcPng);
            $im->setImageColorspace(\Imagick::COLORSPACE_RGB);
            $im->setImageAlphaChannel(\Imagick::ALPHACHANNEL_REMOVE);
            $im = $im->mergeImageLayers(\Imagick::LAYERMETHOD_FLATTEN);

            $w = $im->getImageWidth();
            $h = $im->getImageHeight();

            $x = (int) round($w * $mLeft);
            $y = (int) round($h * $topFrom);
            $cropW = (int) round($w * (1.0 - $mLeft - $mRight));
            $cropH = (int) round($h * ($topTo - $topFrom));

            $cropW = max(64, min($cropW, $w - $x));
            $cropH = max(64, min($cropH, $h - $y));

            $im->cropImage($cropW, $cropH, $x, $y);
            $im->setImagePage(0, 0, 0, 0);

            try {
                $enableTrim = filter_var(env('COG_ENABLE_TRIM', false), FILTER_VALIDATE_BOOL);
                if ($enableTrim) {
                    $fuzz = (float) env('COG_TRIM_FUZZ', 2.0);
                    $im->trimImage(max(0, (int)round($fuzz)));
                    $im->setImagePage(0, 0, 0, 0);
                }
            } catch (\Throwable $e) { /* ignore */ }

            $im->setImageFormat('png');
            $im->writeImage($dstPng);
            $im->clear(); $im->destroy();
        } catch (\Throwable $e) {
            \Log::warning('cogTopContentCrop failed', ['err' => $e->getMessage()]);
            if ($srcPng !== $dstPng) @copy($srcPng, $dstPng);
        }
    }

    private function findBinaryFromEnv(string $envKey, string $fallbackName): ?string
    {
        $candidate = env($envKey);
        if ($candidate && file_exists($candidate)) return $candidate;

        return $this->findBinary($fallbackName);
    }

    private function findBinary(string $name): ?string
    {
        $isWin = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
        try {
            $p = $isWin ? ['where', $name] : ['which', $name];
            $out = $this->run($p, 4);
            $line = trim(preg_split('/\r\n|\r|\n/', $out)[0] ?? '');
            if ($line !== '' && file_exists($line)) return $line;
        } catch (\Throwable $e) {}

        if ($isWin) {
            $candidates = [
                'C:\Program Files\poppler-24.08.0\Library\bin\\'.$name.'.exe',
                'C:\Program Files\poppler-24.07.0\Library\bin\\'.$name.'.exe',
                'C:\Program Files\poppler-24.06.0\Library\bin\\'.$name.'.exe',
                'C:\poppler\bin\\'.$name.'.exe',
            ];
            foreach ($candidates as $c) {
                foreach (glob($c) ?: [] as $match) if (is_file($match)) return $match;
                if (is_file($c)) return $c;
            }
        }

        $dir = env('POPPLER_BIN_DIR') ?: config('services.poppler.bin_dir', null);
        if ($dir) {
            $p = rtrim($dir, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$name.($isWin ? '.exe' : '');
            if (is_file($p)) return $p;
        }
        return null;
    }

    private function run(array $cmd, int $timeout = 30): string
    {
        $p = proc_open($cmd, [['pipe','r'],['pipe','w'],['pipe','w']], $pipes);
        if (!is_resource($p)) throw new \RuntimeException('proc_open failed');
        stream_set_blocking($pipes[1], true); stream_set_blocking($pipes[2], true);
        $start = microtime(true);
        $out = ''; $err = '';
        while (true) {
            $out .= stream_get_contents($pipes[1]);
            $err .= stream_get_contents($pipes[2]);
            $status = proc_get_status($p);
            if (!$status['running']) break;
            if ((microtime(true) - $start) > $timeout) { proc_terminate($p); break; }
            usleep(30000);
        }
        foreach ($pipes as $h) @fclose($h);
        $code = proc_close($p);
        Log::debug('run()', ['cmd'=>implode(' ', $cmd), 'exit'=>$code, 'err'=>$err]);
        if ($code !== 0 && $err) throw new \RuntimeException($err ?: 'process failed');
        return $out.$err;
    }

    /* ===================== TEXT RENDER HELPERS ===================== */

    private function renderCogPrettyWithHeaderTableTotals(array $meta, array $rows, array $totals = []): string
    {
        $v = fn($k) => isset($meta[$k]) ? (string)$meta[$k] : '';

        $lines = [];
        $lines[] = 'Fullname       : '.$v('fullname');
        $lines[] = 'SRCODE         : '.$v('srcode');
        $lines[] = 'College        : '.$v('college');
        $lines[] = 'Program        : '.$v('program');
        $lines[] = 'Semester       : '.$v('semester');
        $lines[] = 'Year Level     : '.$v('year_level');
        $lines[] = 'Academic Year  : '.$v('academic_year');
        $lines[] = '';
        $lines[] = '| # | Course Code | Course Title | Units | Grade | Section | Instructor |';
        $lines[] = '|---|-------------|--------------|-------|-------|---------|------------|';

        foreach ($rows as $r) {
            $lines[] = sprintf(
                '| %s | %s | %s | %s | %s | %s | %s |',
                (string)($r['idx'] ?? ''), (string)($r['code'] ?? ''), (string)($r['title'] ?? ''),
                (string)($r['units'] ?? ''), (string)($r['grade'] ?? ''), (string)($r['section'] ?? ''), (string)($r['instructor'] ?? '')
            );
        }

        $totalUnits = $totals['total_units'] ?? null;
        if ($totalUnits === null) {
            $totalUnits = 0;
            foreach ($rows as $r) $totalUnits += floatval($r['units'] ?? 0);
        }
        $gwa = $totals['gwa'] ?? '';

        $lines[] = '';
        $lines[] = 'Total no of Course : '.count($rows);
        $lines[] = 'Total no of Units  : '.(string)$totalUnits;
        $lines[] = 'General Weighted Average (GWA) : '.(string)$gwa;

        return implode(PHP_EOL, $lines);
    }

    private function mapHeaderToMeta(array $header, array $meta = []): array
    {
        $meta['fullname']      = $meta['fullname']      ?? ($header['Fullname']       ?? '');
        $meta['srcode']        = $meta['srcode']        ?? ($header['SRCODE']         ?? '');
        $meta['college']       = $meta['college']       ?? ($header['College']        ?? '');
        $meta['program']       = $meta['program']       ?? ($header['Program']        ?? '');
        $meta['semester']      = $meta['semester']      ?? ($header['Semester']       ?? '');
        $meta['year_level']    = $meta['year_level']    ?? ($header['Year Level']     ?? '');
        $meta['academic_year'] = $meta['academic_year'] ?? ($header['Academic Year']  ?? '');
        return $meta;
    }

    /* ===================== PDF TEXT (OCR) ===================== */

    private function pdfToText(string $pdfPath): string
    {
        if (!is_file($pdfPath)) return '';

        $bin = env('PDFTOTEXT_PATH') ?: $this->findBinary('pdftotext');
        if (!$bin || !file_exists($bin)) {
            Log::warning('pdftotext not found. Skipping PDF OCR.', ['env' => env('PDFTOTEXT_PATH')]);
            return '';
        }

        try {
            $out = sys_get_temp_dir().DIRECTORY_SEPARATOR.'txt_'.uniqid().'.txt';
            $cmd = [$bin, '-layout', '-nopgbrk', $pdfPath, $out];
            $this->run($cmd, 90);

            $text = is_file($out) ? (string) @file_get_contents($out) : '';
            @unlink($out);
            return trim(str_replace("\r","",$text));
        } catch (\Throwable $e) {
            Log::warning('pdfToText failed', ['err'=>$e->getMessage()]);
            return '';
        }
    }

    /* ===================== VALIDATION (QR vs OCR) ===================== */
    // UPDATED: tolerant split + strict per-code grade comparison that matches your UI

    public function validateCogFromText(Request $req)
    {

            // Read the raw POST data
    $rawText = $request->getContent(); // This will contain the text from your .txt files

    // Now you can parse the raw text and validate it
    $parsedData = $this->parseCogText($rawText);

        $raw = (string) $req->input('text', '');
        if ($raw === '') {
            return response()->json(['ok' => false, 'reason' => 'Empty payload'], 422);
        }

        // Normalize CRLF, NBSP/thin-space/BOM
        $norm = str_replace("\r", '', $raw);
        $norm = preg_replace("/\x{00A0}|\x{202F}|\x{FEFF}/u", ' ', $norm);

        // Split on any "=== OCR TEXT ===" style delimiter (tolerant)
        $parts = preg_split('/\n\s*=+\s*OCR\s*TEXT\s*=+\s*\n/i', $norm, 2);
        if (!$parts || count($parts) < 2) {
            return response()->json(['ok' => false, 'reason' => 'Missing OCR section'], 422);
        }
        $qrBlock  = trim($parts[0]);
        $ocrBlock = trim($parts[1]);

        // Parse QR (markdown table) and OCR (markdown or fixed-width)
        $qrRows  = $this->parseMarkdownTableRows($qrBlock);
        $ocrRows = $this->parseMarkdownTableRows($ocrBlock);
        if (!$ocrRows) $ocrRows = $this->parseFixedWidthOcrRows($ocrBlock);

        if (!$qrRows)  return response()->json(['ok'=>false,'reason'=>'No QR rows found'], 422);
        if (!$ocrRows) return response()->json(['ok'=>false,'reason'=>'No OCR rows found'], 422);

        // Compare by course code (grades normalized: 150→1.50, etc.)
        $diff = $this->diffRowsByCode($qrRows, $ocrRows);

        return response()->json([
            'ok'         => empty($diff['mismatches']) && empty($diff['missing']) && empty($diff['extra']),
            'mismatches' => $diff['mismatches'], // [{code,title,qr,ocr}]
            'missing'    => $diff['missing'],    // present in QR but not in OCR
            'extra'      => $diff['extra'],      // present in OCR but not in QR
        ]);
    }

    public function parseCogText($rawText)
{
    // Split the raw text into lines
    $lines = explode("\n", $rawText);
    $meta = [];
    $grades = [];

    // Parse the header (e.g., fullname, SRCODE, etc.)
    foreach ($lines as $line) {
        if (preg_match('/Fullname\s*:\s*(.*)/', $line, $matches)) {
            $meta['fullname'] = $matches[1];
        } elseif (preg_match('/SRCODE\s*:\s*(.*)/', $line, $matches)) {
            $meta['srcode'] = $matches[1];
        }
        // Continue for other meta fields like College, Program, etc.
    }

    // Parse the grades table
    foreach ($lines as $line) {
        if (preg_match('/(\d+)\s+([A-Za-z0-9]+)\s+(.+)\s+(\d+)\s+(\d\.\d{2})\s+([A-Za-z0-9-]+)\s+(.+)/', $line, $matches)) {
            $grades[] = [
                'index' => $matches[1],
                'course_code' => $matches[2],
                'course_title' => $matches[3],
                'units' => $matches[4],
                'grade' => $matches[5],
                'section' => $matches[6],
                'instructor' => $matches[7],
            ];
        }
    }

    return ['meta' => $meta, 'grades' => $grades];
}


    private function parseMarkdownTableRows(string $block): array
    {
        $rows = [];
        $in = false;
        foreach (preg_split('/\R/', $block) as $line) {
            $line = trim($line);
            if (preg_match('/^\|\s*#\s*\|\s*Course Code/i', $line)) { $in = true; continue; }
            if ($in && preg_match('/^\|\s*-+/', $line)) continue; // separator
            if ($in && preg_match('/^\|\s*\d+\s*\|/',$line)) {
                $cells = array_map('trim', array_map(fn($x)=>trim($x), explode('|', trim($line,'|'))));
                if (count($cells) >= 7) {
                    $rows[] = [
                        'code'       => strtoupper($cells[1] ?? ''),
                        'title'      => $cells[2] ?? '',
                        'units'      => $cells[3] ?? '',
                        'grade'      => $this->normGrade($cells[4] ?? ''),
                        'section'    => $cells[5] ?? '',
                        'instructor' => $cells[6] ?? '',
                    ];
                }
            }
        }
        return $rows;
    }

    /** Robust parser for fixed-width OCR tables (pdftotext -layout). */
    private function parseFixedWidthOcrRows(string $block): array
    {
        $lines = preg_split('/\R/', $block);
        if (!$lines) return [];

        // Find header line
        $hdrIdx = null;
        foreach ($lines as $i => $l) {
            if (preg_match('/#\s+Course\s+Code\s+Course\s+Title\s+Units\s+Grade\s+Section\s+Instructor/i', $l)) {
                $hdrIdx = $i; break;
            }
        }
        if ($hdrIdx === null) {
            foreach ($lines as $i => $l) {
                if (stripos($l, 'Course Code') !== false &&
                    stripos($l, 'Course Title') !== false &&
                    stripos($l, 'Units') !== false &&
                    stripos($l, 'Grade') !== false &&
                    stripos($l, 'Section') !== false &&
                    stripos($l, 'Instructor') !== false) {
                    $hdrIdx = $i; break;
                }
            }
        }
        $startIdx = ($hdrIdx !== null) ? $hdrIdx + 1 : 0;

        $rows = [];
        if ($hdrIdx !== null) {
            $hdr = $lines[$hdrIdx];
            $pos = [
                'idx'        => strpos($hdr, '#'),
                'code'       => strpos($hdr, 'Course Code'),
                'title'      => strpos($hdr, 'Course Title'),
                'units'      => strpos($hdr, 'Units'),
                'grade'      => strpos($hdr, 'Grade'),
                'section'    => strpos($hdr, 'Section'),
                'instructor' => strpos($hdr, 'Instructor'),
            ];
            $posOk = !in_array(false, $pos, true);

            for ($i = $startIdx; $i < count($lines); $i++) {
                $line = rtrim($lines[$i], "\r\n");
                if (trim($line) === '' || preg_match('/^\s*[-\u2500]+/u', $line)) continue;
                if (preg_match('/\*+\s*NOTHING FOLLOWS/i', $line)) break;
                if (preg_match('/^\s*(Total no of|General Weighted Average)/i', $line)) break;

                if ($posOk) {
                    $maybeIdx = trim(substr($line, $pos['idx'], max(0, $pos['code'] - $pos['idx'])));
                    if ($maybeIdx === '' || !preg_match('/^\d+$/', $maybeIdx)) continue;

                    $code    = strtoupper(trim(substr($line, $pos['code'],    max(0, $pos['title'] - $pos['code']))));
                    $title   = trim(substr($line, $pos['title'],   max(0, $pos['units'] - $pos['title'])));
                    $units   = trim(substr($line, $pos['units'],   max(0, $pos['grade'] - $pos['units'])));
                    $grade   = trim(substr($line, $pos['grade'],   max(0, $pos['section'] - $pos['grade'])));
                    $section = trim(substr($line, $pos['section'], max(0, $pos['instructor'] - $pos['section'])));
                    $instr   = trim(substr($line, $pos['instructor']));

                    if ($code === '' || $title === '') continue;

                    $rows[] = [
                        'code'       => $code,
                        'title'      => $title,
                        'units'      => $units,
                        'grade'      => $this->normGrade($grade),
                        'section'    => $section,
                        'instructor' => $instr,
                    ];
                    continue;
                }

                if (preg_match('/^\s*(\d+)\s+([A-Z0-9][A-Z0-9 ]*?)\s{2,}(.+?)\s{2,}(\d+(?:\.\d+)?)\s{2,}([\d.]+)\s{2,}([A-Z0-9\-]+)\s{2,}(.+?)\s*$/u', $line, $m)
                    || preg_match('/^\s*(\d+)\s+([A-Z0-9][A-Z0-9 ]*?)\s{2,}(.+?)\s+(\d+(?:\.\d+)?)\s+([\d.]+)\s+([A-Z0-9\-]+)\s{1,}(.+?)\s*$/u', $line, $m)) {
                    $rows[] = [
                        'code'       => strtoupper(trim($m[2] ?? '')),
                        'title'      => trim($m[3] ?? ''),
                        'units'      => trim($m[4] ?? ''),
                        'grade'      => $this->normGrade(trim($m[5] ?? '')),
                        'section'    => trim($m[6] ?? ''),
                        'instructor' => trim($m[7] ?? ''),
                    ];
                }
            }
            return $rows;
        }

        // last resort: forgiving regex scan
        for ($i = $startIdx; $i < count($lines); $i++) {
            $line = rtrim($lines[$i], "\r\n");
            if (trim($line) === '' || preg_match('/^\s*[-\u2500]+/u', $line)) continue;
            if (preg_match('/\*+\s*NOTHING FOLLOWS/i', $line)) break;
            if (preg_match('/^\s*(Total no of|General Weighted Average)/i', $line)) break;

            if (preg_match('/^\s*(\d+)\s+([A-Z0-9][A-Z0-9 ]*?)\s{2,}(.+?)\s{2,}(\d+(?:\.\d+)?)\s{2,}([\d.]+)\s{2,}([A-Z0-9\-]+)\s{2,}(.+?)\s*$/u', $line, $m)
                || preg_match('/^\s*(\d+)\s+([A-Z0-9][A-Z0-9 ]*?)\s{2,}(.+?)\s+(\d+(?:\.\d+)?)\s+([\d.]+)\s+([A-Z0-9\-]+)\s{1,}(.+?)\s*$/u', $line, $m)) {
                $rows[] = [
                    'code'       => strtoupper(trim($m[2] ?? '')),
                    'title'      => trim($m[3] ?? ''),
                    'units'      => trim($m[4] ?? ''),
                    'grade'      => $this->normGrade(trim($m[5] ?? '')),
                    'section'    => trim($m[6] ?? ''),
                    'instructor' => trim($m[7] ?? ''),
                ];
            }
        }
        return $rows;
    }

    

    private function normGrade(string $g): string
    {
        $t = strtoupper(trim(preg_replace('/\s+/', '', $g)));
        if ($t === '100') return '1.00';
        if ($t === '125') return '1.25';
        if ($t === '150') return '1.50';
        if ($t === '175') return '1.75';
        if ($t === '200') return '2.00';
        if (in_array($t, ['INC','INCOMPLETE','DROP','DRP','W'], true)) return $t;
        return $t;
    }

    private function diffRowsByCode(array $qrRows, array $ocrRows): array
    {
        $canon = fn($s) => preg_replace('/\s+/', ' ', strtoupper(trim($s ?? '')));
        $qrBy  = [];
        foreach ($qrRows as $r) $qrBy[$canon($r['code'])] = $r;
        $ocrBy = [];
        foreach ($ocrRows as $r) $ocrBy[$canon($r['code'])] = $r;

        $mismatches = [];
        foreach ($ocrBy as $k => $ocr) {
            if (!isset($qrBy[$k])) continue;
            $qr = $qrBy[$k];
            if ($qr['grade'] !== $ocr['grade']) {
                $mismatches[] = [
                    'code' => $qr['code'],
                    'title'=> $qr['title'],
                    'qr'   => $qr['grade'],
                    'ocr'  => $ocr['grade'],
                ];
            }
        }

        $missing = []; // in QR but not in OCR
        foreach ($qrBy as $k => $qr) if (!isset($ocrBy[$k])) $missing[] = $qr;

        $extra = [];   // in OCR but not in QR
        foreach ($ocrBy as $k => $ocr) if (!isset($qrBy[$k])) $extra[] = $ocr;

        return compact('mismatches','missing','extra');
    }
}
