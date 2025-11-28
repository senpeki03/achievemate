<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Services\StudentProgressService;
use Zxing\QrReader;
use App\Models\StudentGrade;
use App\Models\AcademicYear;
use App\Models\Grades;

class StudentGradeController extends Controller
{
    /** Main page */
    public function index()
    {
        return view('student.studentgrade');
    }

    /**
     * STEP 1: Upload PDF → Process immediately and return all data
     */
    public function uploadPreview(Request $request)
    {
        $request->validate([
            'cog_file' => ['required', 'file', 'mimes:pdf', 'max:10240'],
        ], [
            'cog_file.mimes' => 'Please upload a PDF file only.',
        ]);

        $this->cleanupTempFiles();

        try {
            // Store temporarily (for preview/QR)
            $pdfPath    = $request->file('cog_file')->store('temp/cog', 'public');
            $pdfAbsPath = storage_path('app/public/' . $pdfPath);

            // ---------- Generate Cropped PNG Preview ----------
            $finalPng        = storage_path('app/grades/grades_upload.png');
            $previewImageUrl = null;

            // Ensure grades directory exists
            Storage::disk('local')->makeDirectory('grades');

            // Clean previous preview
            @unlink($finalPng);

            try {
                // Render PDF to PNG using multiple fallback methods
                $rendered = $this->renderPdfPage1ToPng($pdfAbsPath, $finalPng);

                if ($rendered && is_file($finalPng)) {
                    // Convert PNG to base64 for immediate display
                    $pngContent      = file_get_contents($finalPng);
                    $previewImageUrl = 'data:image/png;base64,' . base64_encode($pngContent);

                    Log::info('PNG generated successfully', [
                        'path'        => $finalPng,
                        'size'        => filesize($finalPng),
                        'base64_size' => strlen($previewImageUrl),
                    ]);
                }
            } catch (\Throwable $e) {
                Log::error('PNG generation failed', ['error' => $e->getMessage()]);
                // Continue without PNG - this is not a fatal error
            }

            // ---------- QR Scanning ----------
            $qrData = ['text' => null, 'url' => null, 'type' => 'unknown'];

            if (is_file($finalPng)) {
                try {
                    $qr   = new QrReader($finalPng);
                    $text = trim((string) $qr->text());
                    if ($text !== '') {
                        $qrData['text'] = $text;
                        if (preg_match('#https?://\S+#', $text, $m)) {
                            $qrData['url']  = $m[0];
                            $qrData['type'] = 'url';
                        } elseif (filter_var($text, FILTER_VALIDATE_URL)) {
                            $qrData['url']  = $text;
                            $qrData['type'] = 'url';
                        } else {
                            $qrData['type'] = 'text';
                        }
                    }
                } catch (\Throwable $e) {
                    Log::error('QR Scanning failed', ['error' => $e->getMessage()]);
                }
            }

            // ---------- Text Extraction ----------
            $extractedText = $this->extractTextFromPDF($pdfAbsPath);
            Log::info('Raw extracted text length: ' . strlen($extractedText));
            Log::info('Raw extracted text sample: ' . substr($extractedText, 0, 500));

            $normalizedText = $this->normalizePdfTextForDisplay($extractedText);

            // ---------- Parse Text Data Immediately ----------
            $parsedData = $this->parseExtractedText($extractedText);
            Log::info('Parsed data result', [
                'meta_found'    => !empty($parsedData['meta']),
                'courses_found' => count($parsedData['courses']),
                'meta'          => $parsedData['meta'],
            ]);

            // ---------- Write the text output file ----------
            try {
                $combined = $this->buildCogSourceLine($qrData) . "\n";
                $combined .= now()->format('m/d/y, g:i A')
                    . "                         Batangas State University - Student Copy of Grades\n\n";

                // Include parsed data in the output file for frontend fallback
                if (!empty($parsedData['courses'])) {
                    $combined .= "PARSED DATA:\n";
                    $combined .= "Fullname: " . ($parsedData['meta']['fullname'] ?? '') . "\n";
                    $combined .= "SRCODE: " . ($parsedData['meta']['srcode'] ?? '') . "\n";
                    $combined .= "College: " . ($parsedData['meta']['college'] ?? '') . "\n";
                    $combined .= "Academic Year: " . ($parsedData['meta']['academic_year'] ?? '') . "\n";
                    $combined .= "Program: " . ($parsedData['meta']['program'] ?? '') . "\n";
                    $combined .= "Semester: " . ($parsedData['meta']['semester'] ?? '') . "\n";
                    $combined .= "Year Level: " . ($parsedData['meta']['year_level'] ?? '') . "\n\n";

                    $combined .= "COURSES:\n";
                    foreach ($parsedData['courses'] as $course) {
                        $combined .= "{$course['course_code']} | {$course['course_title']} | {$course['units']} | {$course['grade']} | {$course['section']} | {$course['instructor']}\n";
                    }

                    $combined .= "\nSUMMARY:\n";
                    $combined .= "Total Courses: " . count($parsedData['courses']) . "\n";
                    $combined .= "Total Units: " . $parsedData['total_units'] . "\n";
                    $combined .= "GWA: " . $parsedData['gwa'] . "\n\n";
                }

                // ADD THE ACTUAL EXTRACTED TEXT (not normalized) to the output file
                $combined .= "RAW EXTRACTED TEXT:\n" . $extractedText;

                // Use DIRECT file operations - Storage facade has issues
                $gradesDir = storage_path('app/grades');
                $filePath  = $gradesDir . '/grades_qr_output.txt';

                Log::info('=== SAVING GRADES OUTPUT FILE ===');
                Log::info('Using direct file operations');
                Log::info('File path: ' . $filePath);

                // Ensure directory exists
                if (!is_dir($gradesDir)) {
                    mkdir($gradesDir, 0755, true);
                    Log::info('Created directory: ' . $gradesDir);
                }

                // Write file directly
                $bytesWritten = file_put_contents($filePath, $combined);

                if ($bytesWritten !== false) {
                    // Verify the file was actually written
                    $fileExists = file_exists($filePath);
                    $fileSize   = $fileExists ? filesize($filePath) : 0;

                    Log::info('FILE SAVE SUCCESS', [
                        'bytes_written'       => $bytesWritten,
                        'file_exists'         => $fileExists,
                        'file_size'           => $fileSize,
                        'files_in_directory'  => scandir($gradesDir),
                    ]);
                } else {
                    Log::error('DIRECT FILE WRITE FAILED');
                    $error = error_get_last();
                    Log::error('Error details:', $error ?: 'No error info');
                }
            } catch (\Throwable $e) {
                Log::error('FILE SAVE EXCEPTION: ' . $e->getMessage());
                Log::error('Stack trace: ' . $e->getTraceAsString());
            }

            // Store essentials in session
            session([
                'cog_pdf_path'       => $pdfPath,
                'cog_original_name'  => $request->file('cog_file')->getClientOriginalName(),
                'qr_data'            => $qrData,
                'scanned_at'         => Carbon::now()->toDateTimeString(),
                'preview_image_url'  => $previewImageUrl,
                'parsed_data'        => $parsedData,
            ]);

            return response()->json([
                'success'        => true,
                'message'        => 'PDF processed successfully!',
                'text_extracted' => !empty($extractedText),
                'preview_image'  => $previewImageUrl,
                'has_preview'    => !empty($previewImageUrl),
                'parsed_data'    => $parsedData,
                'extracted_text' => $normalizedText,
            ]);
        } catch (\Throwable $e) {
            Log::error('Upload preview failed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Processing failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function debugStorage()
    {
        try {
            $gradesPath = 'grades';
            $filePath   = 'grades/grades_qr_output.txt';

            $results = [
                'storage_path'              => storage_path(),
                'grades_directory_exists'   => Storage::disk('local')->exists($gradesPath),
                'grades_directory_full_path'=> storage_path('app/' . $gradesPath),
                'can_create_directories'    => is_writable(storage_path('app')),
            ];

            // Try to create directory if it doesn't exist
            if (!Storage::disk('local')->exists($gradesPath)) {
                $created                      = Storage::disk('local')->makeDirectory($gradesPath);
                $results['directory_created'] = $created;
            }

            // Try to write a test file
            $testContent                  = "Test file created at: " . now()->toDateTimeString();
            $testWritten                  = Storage::disk('local')->put($filePath, $testContent);
            $results['test_file_written'] = $testWritten;

            // Check if file exists after writing
            $results['file_exists_after_write'] = Storage::disk('local')->exists($filePath);

            if ($results['file_exists_after_write']) {
                $results['file_content'] = Storage::disk('local')->get($filePath);
                $results['file_size']    = Storage::disk('local')->size($filePath);
            }

            return response()->json($results);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Extract text from PDF using Smalot PDF Parser only (no shell_exec)
     */
    private function extractTextFromPDF($pdfPath)
    {
        try {
            if (!class_exists(\Smalot\PdfParser\Parser::class)) {
                Log::error('Smalot PDF Parser not installed');
                return '';
            }

            Log::info('Starting text extraction using Smalot PDF Parser');

            $parser = new \Smalot\PdfParser\Parser();
            $pdf    = $parser->parseFile($pdfPath);
            $text   = $pdf->getText();

            Log::info("Text extracted via Smalot, length: " . strlen($text));

            return is_string($text) ? trim($text) : '';
        } catch (\Exception $e) {
            Log::error("PDF parsing failed: " . $e->getMessage());
            return '';
        }
    }

    private function parseExtractedText($text)
    {
        $lines = preg_split('/\r\n|\r|\n/', $text);
        $data  = [
            'meta' => [
                'fullname'      => '',
                'srcode'        => '',
                'college'       => '',
                'academic_year' => '',
                'program'       => '',
                'semester'      => '',
                'year_level'    => '',
            ],
            'courses'     => [],
            'total_units' => 0,
            'gwa'         => 'N/A',
        ];

        Log::info('Starting text parsing with ' . count($lines) . ' lines');

        foreach ($lines as $index => $line) {
            $line = trim($line);
            if (empty($line)) continue;

            // --- META FIELDS (same as before) ---------------------------------
            if (preg_match('/SRCODE:\s*([\d\-]+)/i', $line, $matches)) {
                $data['meta']['srcode'] = trim($matches[1]);
            }
            if (preg_match('/Fullname:\s*([^,]+,\s*[^,\t]+(?:\s+[A-Z]\.)?)(?:\s+SRCODE\s*:|\t|$)/i', $line, $matches)) {
                $data['meta']['fullname'] = trim($matches[1]);
            }
            if (preg_match('/College:\s*(.+?)(?:\s+Academic Year\s*:|$)/i', $line, $matches)) {
                $data['meta']['college'] = trim($matches[1]);
            }
            if (preg_match('/Program:\s*(.+?)(?:\s+Semester\s*:|$)/i', $line, $matches)) {
                $data['meta']['program'] = trim($matches[1]);
            }
            if (preg_match('/Year Level:\s*(.+)/i', $line, $matches)) {
                $data['meta']['year_level'] = trim($matches[1]);
            }
            if (preg_match('/Academic Year:\s*(.+)/i', $line, $matches)) {
                $data['meta']['academic_year'] = trim($matches[1]);
            }
            if (preg_match('/Semester:\s*(.+)/i', $line, $matches)) {
                $data['meta']['semester'] = trim($matches[1]);
            }

            // --- COURSE ROWS ---------------------------------------------------
            // NOTE: \s* after grade → puwedeng walang space bago ang section
            if (preg_match(
                '/^(\d+)\s+([A-Z]+\s+\d+)\s+(.+?)\s+(\d+(?:\.\d+)?)\s+' .
                '((?:[0-4](?:\.\d{1,3})?|(?:INC|DRP|W|PASS)(?:\/[0-4](?:\.\d{1,3})?)?))\s*' .
                '([A-Z0-9\-]+)\s+(.+)$/iu',
                $line,
                $matches
            )) {
                $course = [
                    'course_number' => $matches[1],
                    'course_code'   => trim($matches[2]),
                    'course_title'  => trim($matches[3]),
                    'units'         => floatval($matches[4]),
                    'grade'         => trim($matches[5]),      // e.g. "INC/2.00"
                    'section'       => trim($matches[6]),      // e.g. "IT-BA-3301"
                    'instructor'    => trim($matches[7]),
                ];
                $data['courses'][]   = $course;
                $data['total_units'] += $course['units'];

                Log::info('Parsed course: ' . $course['course_code'], [
                    'grade'   => $course['grade'],
                    'section' => $course['section'],
                ]);
            }
        }

        // alt parser kung sakaling wala talaga
        if (empty($data['courses'])) {
            $this->parseCoursesAlternative($lines, $data);
        }

        if (!empty($data['courses'])) {
            $data['gwa'] = $this->calculateGWA($data['courses']);
        }

        Log::info('Final parsing result', $data);

        return $data;
    }


    /**
     * Alternative course parsing for different formats
     */
    private function parseCoursesAlternative(&$lines, &$data)
    {
        $inCoursesSection = false;

        foreach ($lines as $line) {
            $line = trim($line);

            if (str_contains($line, 'Course Code') || str_contains($line, '#Course')) {
                $inCoursesSection = true;
                continue;
            }

            if ($inCoursesSection && !empty($line)) {
                if (preg_match(
                    '/^(\d+)\s+([A-Z]+\s+\d+)\s+(.+?)\s+(\d+\.?\d*)\s+' .
                    '((?:[0-4](?:\.\d{1,3})?|(?:INC|DRP|W|PASS)(?:\/[0-4](?:\.\d{1,3})?)?))/i',
                    $line,
                    $matches
                )) {
                    $course = [
                        'course_number' => $matches[1],
                        'course_code'   => trim($matches[2]),
                        'course_title'  => trim($matches[3]),
                        'units'         => floatval($matches[4]),
                        'grade'         => trim($matches[5]), // may "INC/2.00"
                        'section'       => '',
                        'instructor'    => '',
                    ];
                    $data['courses'][]   = $course;
                    $data['total_units'] += $course['units'];

                    Log::info('Parsed course (alternative): ' . $course['course_code'], [
                        'grade' => $course['grade'],
                    ]);
                }
            }
        }
    }


    private function calculateGWA(array $courses): string
    {
        $totalWeight = 0;
        $totalUnits  = 0;

        foreach ($courses as $course) {
            $units = floatval($course['units']);

            $gradeStr = strtoupper(trim((string) $course['grade']));
            $grade    = 0.0;

            // Get first numeric value inside the grade string (handles "INC/3.00")
            if (preg_match('/(\d+(?:\.\d+)?)/', $gradeStr, $m)) {
                $grade = (float) $m[1];
            }

            // Only include numeric grades in GWA calculation
            if ($grade > 0 && $units > 0 && $grade <= 5.0) {
                $totalWeight += $grade * $units;
                $totalUnits  += $units;
            }
        }

        return $totalUnits > 0 ? number_format($totalWeight / $totalUnits, 4) : 'N/A';
    }

    /* ========================= PDF to PNG Rendering ========================= */

    private function renderPdfPage1ToPng(string $pdfAbs, string $outPngAbs): bool
    {
        $ok = false;

        // Try Imagick first (no shell_exec required)
        if (extension_loaded('imagick')) {
            try {
                $im = new \Imagick();
                $im->setResolution(300, 300);
                $im->readImage($pdfAbs . '[0]');
                $im->setImageBackgroundColor('white');
                $im->setImageAlphaChannel(\Imagick::ALPHACHANNEL_REMOVE);
                $im = $im->mergeImageLayers(\Imagick::LAYERMETHOD_FLATTEN);

                // Apply COG crop tuning with NEW parameters
                $width  = $im->getImageWidth();
                $height = $im->getImageHeight();

                $cropLeft   = (float) env('COG_CROP_LEFT', 0.05);
                $cropRight  = (float) env('COG_CROP_RIGHT', 0.96);
                $cropTop    = (float) env('COG_CROP_TOP', 0.04);
                $cropBottom = (float) env('COG_CROP_BOTTOM', 0.495);

                // Calculate crop dimensions
                $x          = (int) ($width * $cropLeft);
                $y          = (int) ($height * $cropTop);
                $cropWidth  = (int) ($width * ($cropRight - $cropLeft));
                $cropHeight = (int) ($height * ($cropBottom - $cropTop));

                Log::info('Imagick cropping parameters', [
                    'width'          => $width,
                    'height'         => $height,
                    'crop_x'         => $x,
                    'crop_y'         => $y,
                    'crop_width'     => $cropWidth,
                    'crop_height'    => $cropHeight,
                    'bottom_percent' => $cropBottom,
                ]);

                $im->cropImage($cropWidth, $cropHeight, $x, $y);
                $im->normalizeImage();
                $im->borderImage('white', 15, 15);
                $im->setImageFormat('png');
                $im->setImageCompressionQuality(95);
                $im->writeImage($outPngAbs);
                $im->clear();
                $im->destroy();
                $ok = is_file($outPngAbs);

                if ($ok) {
                    Log::info('PDF rendered successfully using Imagick');
                    return true;
                }
            } catch (\Throwable $e) {
                Log::warning('Imagick PDF to PNG failed', ['error' => $e->getMessage()]);
            }
        }

        // Fallback: shell_exec disabled, skip pdftoppm/pdftopng
        Log::warning('shell_exec is disabled, using Imagick only for PDF rendering');

        return $ok;
    }

    /* ========================= Utility Methods ========================= */

    private function findBinary(string $name): ?string
    {
        // Since shell_exec is disabled, return null for all binary searches
        Log::warning('Binary search disabled due to shell_exec restriction', ['binary' => $name]);
        return null;
    }

    private function run(array $cmd, int $timeout = 30): string
    {
        // This method will not be used since shell_exec is disabled
        throw new \RuntimeException('Shell execution is disabled on this server');
    }

    /* ========================= Database Methods ========================= */

    /**
     * Save image from base64 data
     */
    private function saveImageFromBase64(
        string $base64Image,
        int $studentId,
        string $semester,
        string $academicYear
    ): bool {
        try {
            // Remove data:image/png;base64, prefix if present
            if (strpos($base64Image, 'base64,') !== false) {
                $base64Image = substr($base64Image, strpos($base64Image, 'base64,') + 7);
            }

            $imageData = base64_decode($base64Image);
            if ($imageData === false) {
                Log::error('Failed to decode base64 image');
                return false;
            }

            Log::info('Decoded image data', [
                'student_id'     => $studentId,
                'image_size'     => strlen($imageData),
                'semester'       => $semester,
                'academic_year'  => $academicYear,
            ]);

            // Hanapin kung may existing record na para sa STUDENT + SEM + AY
            $gradesRecord = Grades::where('Student_id', $studentId)
                ->where('sem', $semester)
                ->where('academic_year', $academicYear)
                ->first();

            if ($gradesRecord) {
                // Update existing
                $gradesRecord->update([
                    'image'         => $imageData,
                    'sem'           => $semester,
                    'academic_year' => $academicYear,
                ]);

                Log::info('Updated existing grades record with image', [
                    'grades_id'      => $gradesRecord->Grades_id,
                    'student_id'     => $studentId,
                    'semester'       => $semester,
                    'academic_year'  => $academicYear,
                ]);
            } else {
                // Create NEW row per sem + academic year
                try {
                    $gradesRecord = Grades::create([
                        'Student_id'    => $studentId,
                        'image'         => $imageData,
                        'sem'           => $semester,
                        'academic_year' => $academicYear,
                    ]);

                    Log::info('Created new grades record with image', [
                        'grades_id'      => $gradesRecord->Grades_id,
                        'student_id'     => $studentId,
                        'semester'       => $semester,
                        'academic_year'  => $academicYear,
                    ]);
                } catch (\Exception $e) {
                    Log::error('Failed to create grades record', [
                        'student_id'    => $studentId,
                        'semester'      => $semester,
                        'academic_year' => $academicYear,
                        'error'         => $e->getMessage(),
                    ]);
                    return false;
                }
            }

            // Verify the image was saved
            $verifiedRecord = Grades::find($gradesRecord->Grades_id);
            $imageSaved     = !empty($verifiedRecord->image);

            Log::info('Image save verification', [
                'verified'         => $imageSaved,
                'saved_image_size' => $imageSaved ? strlen($verifiedRecord->image) : 0,
            ]);

            return $imageSaved;
        } catch (\Throwable $e) {
            Log::error('Failed to save image from base64', [
                'student_id'    => $studentId,
                'semester'      => $semester,
                'academic_year' => $academicYear,
                'error'         => $e->getMessage(),
                'trace'         => $e->getTraceAsString(),
            ]);
            return false;
        }
    }

    /**
     * Get student ID from session
     */
    private function getStudentId(): ?int
    {
        return (int)(
            session('Student_id') ??
            session('studentID') ??
            session('student_id') ??
            optional(auth()->user())->Student_id ??
            0
        );
    }

    /**
     * STEP 2: Final submission (moves PDF)
     */
    public function processCog(Request $request)
    {
        $pdfPath    = session('cog_pdf_path');
        $pdfAbsPath = storage_path('app/public/' . $pdfPath);

        if (!$pdfPath || !is_file($pdfAbsPath)) {
            return redirect()->route('student.studentgrade')
                ->withErrors(['cog_file' => 'PDF file not found. Please upload again.']);
        }

        // Move to permanent
        $safeName     = $this->makeFilenameSafe(session('cog_original_name'));
        $permanentRel = 'cog/' . $safeName;
        $permanentAbs = storage_path('app/public/' . $permanentRel);
        File::ensureDirectoryExists(dirname($permanentAbs));
        @rename($pdfAbsPath, $permanentAbs);

        // Clear session junk
        session()->forget([
            'cog_pdf_path',
            'cog_preview_path',
            'cog_original_name',
            'qr_data',
            'scanned_at',
            'preview_image_url',
        ]);

        return redirect()->route('student.studentgrade')
            ->with('cog_success', 'COG submitted successfully! Grades have been extracted and saved.')
            ->with('file_path', 'storage/' . $permanentRel);
    }

    /**
     * Overwrite the text file (optional patch endpoint)
     */
    public function storeQrOutput(Request $request)
    {
        try {
            Storage::disk('local')->makeDirectory('grades');
            $raw = (string) $request->input('raw_text', '');
            Storage::disk('local')->put('grades/grades_qr_output.txt', $raw);

            return response()->json([
                'ok'   => true,
                'path' => 'storage/app/grades/grades_qr_output.txt',
                'size' => strlen($raw),
            ]);
        } catch (\Throwable $e) {
            Log::error('storeQrOutput failed', ['err' => $e->getMessage()]);
            return response()->json(['ok' => false, 'message' => 'Write failed'], 500);
        }
    }

    /**
     * Single source-of-truth file for Step 2 fetch()
     */
    public function qrOutput()
    {
        try {
            $rel = 'grades/grades_qr_output.txt';
            if (!Storage::disk('local')->exists($rel)) {
                return response('', 200)->header('Content-Type', 'text/plain; charset=utf-8');
            }
            $txt = Storage::disk('local')->get($rel);
            return response($txt, 200)->header('Content-Type', 'text/plain; charset=utf-8');
        } catch (\Throwable $e) {
            Log::warning('qrOutput read failed', ['err' => $e->getMessage()]);
            return response('', 200)->header('Content-Type', 'text/plain; charset=utf-8');
        }
    }

    /**
     * Get the saved PNG image from database
     */
    public function getSavedPng()
    {
        try {
            $studentId = $this->getStudentId();
            if (!$studentId) {
                return response()->json(['error' => 'Student not found'], 404);
            }

            $gradesRecord = Grades::where('Student_id', $studentId)->first();
            if (!$gradesRecord || empty($gradesRecord->image)) {
                return response()->json(['error' => 'No saved image found'], 404);
            }

            return response($gradesRecord->image)
                ->header('Content-Type', 'image/png')
                ->header('Cache-Control', 'no-cache, no-store, must-revalidate');
        } catch (\Throwable $e) {
            Log::error('Failed to retrieve PNG from database', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Image retrieval failed'], 500);
        }
    }

    /**
     * Save parsed COG rows to student_grades with image
     */
    public function storeFromCog(Request $request)
    {
        try {
            $data = $request->validate([
                'meta.semester'      => 'nullable',
                'meta.academic_year' => 'nullable',
                'rows'               => 'required|array|min:1',
                'rows.*.code'        => 'required',
                'rows.*.grade'       => 'nullable',
                'rows.*.section'     => 'nullable',
                'rows.*.instructor'  => 'nullable',
                'rows.*.title'       => 'nullable',
                'rows.*.units'       => 'nullable',
                'image_data'         => 'nullable|string',
            ]);

            // Convert all data to strings to ensure consistency
            $data['meta']['semester']      = (string)($data['meta']['semester'] ?? '');
            $data['meta']['academic_year'] = (string)($data['meta']['academic_year'] ?? '');

            foreach ($data['rows'] as &$row) {
                $row['code']       = (string)($row['code'] ?? '');
                $row['grade']      = (string)($row['grade'] ?? '');
                $row['section']    = (string)($row['section'] ?? '');
                $row['instructor'] = (string)($row['instructor'] ?? '');
                $row['title']      = (string)($row['title'] ?? '');
                $row['units']      = (string)($row['units'] ?? '');
            }
            unset($row);

            Log::info('Incoming COG data for saving:', [
                'meta'        => $data['meta'],
                'rows_count'  => count($data['rows']),
                'has_image'   => !empty($data['image_data']),
                'sample_row'  => $data['rows'][0] ?? 'No rows',
            ]);

            // Resolve Student_id
            $studentId = $this->getStudentId();
            if ($studentId <= 0) {
                return response()->json(['ok' => false, 'message' => 'No Student_id found in session'], 401);
            }

            // Normalize semester (FIRST / SECOND / MIDYEAR / SUMMER / SUMMER2)
            $semester = $this->normalizeSemester((string)($data['meta']['semester'] ?? ''));
            // Label galing mismo sa COG (e.g. "2023-2024")
            $label = trim($data['meta']['academic_year'] ?? '');

            $imageSaved = false;
            if (!empty($data['image_data']) && $label !== '') {
                try {
                    // Save image per Student + Semester + Academic Year
                    $imageSaved = $this->saveImageFromBase64(
                        $data['image_data'],
                        $studentId,
                        $semester,
                        $label
                    );

                    Log::info('Image save result', [
                        'success'       => $imageSaved,
                        'student_id'    => $studentId,
                        'semester'      => $semester,
                        'academic_year' => $label,
                    ]);
                } catch (\Throwable $e) {
                    Log::warning('Image saving failed but continuing with grade data', [
                        'student_id'    => $studentId,
                        'semester'      => $semester,
                        'academic_year' => $label,
                        'error'         => $e->getMessage(),
                    ]);
                    $imageSaved = false;
                }
            }

            // ================== Academic Year mapping (with auto-create) ==================
            $academicYearId = null;

            if ($label !== '') {
                // 1) Try to find existing AY by label (e.g. "2022-2023")
                $ay = AcademicYear::byLabel($label)->first();
                if ($ay) {
                    $academicYearId = (int)$ay->academic_year_id;
                    Log::info('Matched existing Academic Year by label', [
                        'label' => $label,
                        'id'    => $academicYearId,
                    ]);
                } else {
                    // 2) If not found → CREATE a new Academic Year row
                    $newAy = AcademicYear::create([
                        'label'      => $label,
                        'start_date' => null,
                        'end_date'   => null,
                        'is_current' => 0,
                    ]);

                    $academicYearId = (int)$newAy->academic_year_id;

                    Log::info('Created NEW Academic Year from COG', [
                        'label' => $label,
                        'id'    => $academicYearId,
                    ]);
                }
            }

            // 3) Fallback: use current AY if still null
            if ($academicYearId === null) {
                $currentAy = AcademicYear::current()->first();
                if ($currentAy) {
                    $academicYearId = (int)$currentAy->academic_year_id;
                    Log::info('Falling back to current Academic Year', [
                        'id'    => $academicYearId,
                        'label' => $currentAy->label ?? null,
                    ]);
                }
            }

            // 4) Still nothing? Then error out.
            if ($academicYearId === null) {
                return response()->json([
                    'ok'      => false,
                    'reason'  => 'UNKNOWN_ACADEMIC_YEAR',
                    'message' => 'Academic year not found.',
                ], 422);
            }
            // =======================================================================

            // Detect which code columns exist in curriculum_subjects
            $codeColumns = array_values(array_filter([
                Schema::hasColumn('curriculum_subjects', 'subject_code') ? 'subject_code' : null,
                Schema::hasColumn('curriculum_subjects', 'code')         ? 'code'         : null,
                Schema::hasColumn('curriculum_subjects', 'coursecode')   ? 'coursecode'   : null,
                Schema::hasColumn('curriculum_subjects', 'course_code')  ? 'course_code'  : null,
            ]));
            if (empty($codeColumns)) {
                return response()->json([
                    'ok'     => false,
                    'reason' => 'NO_CODE_COLUMNS',
                    'message'=> 'No recognizable code column found in curriculum_subjects.',
                ], 500);
            }

            $inserted = 0;
            $updated  = 0;
            $unmapped = [];

            DB::beginTransaction();

            foreach ($data['rows'] as $r) {
                $rawCode   = trim((string)($r['code'] ?? ''));
                $codeNoSpc = preg_replace('/\s+/', '', $rawCode);

                // DEBUG: makita natin kung pumapasok talaga yung row na may INC/3.00
                Log::info('COG ROW RECEIVED', [
                    'code'  => $rawCode,
                    'grade' => $r['grade'] ?? null,
                ]);

                // SUBJECT LOOKUP (FK)
                $subject = DB::table('curriculum_subjects')
                    ->where(function($q) use ($codeColumns, $rawCode, $codeNoSpc) {
                        foreach ($codeColumns as $col) {
                            $q->orWhere($col, $rawCode)
                              ->orWhereRaw("REPLACE($col,' ','') = ?", [$codeNoSpc]);
                        }
                    })
                    ->first();

                if (!$subject) {
                    $unmapped[] = $rawCode;
                    continue;
                }
                $subjectId = (int)$subject->subject_id;

                // ------------------ STORE RAW GRADE STRING ------------------
                $gradeStr = trim((string)($r['grade'] ?? ''));
                // Ito ang ma-i-store sa DB (literal, pwedeng "INC/3.00", "3.00", "DRP", etc.)
                $gradeVal = $gradeStr !== '' ? $gradeStr : null;
                // ------------------------------------------------------------

                // remarks is NOT NULL in your schema
                $remarks      = $this->makeShortRemarks($r['grade'] ?? null);
                $remarks      = mb_substr($remarks, 0, 11);
                $semesterSafe = mb_substr($semester, 0, 10);

                // Upsert key
                $where = [
                    'Student_id'       => $studentId,
                    'course_code'      => $rawCode,
                    'semester'         => $semester,
                    'academic_year_id' => $academicYearId,
                ];

                $payload = [
                    'Student_id'       => $studentId,
                    'course_code'      => $rawCode,
                    'subject_id'       => $subjectId,
                    'academic_year_id' => $academicYearId,
                    'semester'         => $semesterSafe,
                    'grade'            => $gradeVal, // raw string (INC/3.00)
                    'section'          => (string)($r['section'] ?? ''),
                    'instructor'       => (string)($r['instructor'] ?? ''),
                    'remarks'          => $remarks,
                ];

                $existing = StudentGrade::where($where)->first();

                if ($existing) {
                    $existing->fill($payload);
                    $existing->save();
                    $updated++;
                } else {
                    StudentGrade::create($payload);
                    $inserted++;
                }
            }

            if (!empty($unmapped)) {
                DB::rollBack();
                return response()->json([
                    'ok'                   => false,
                    'reason'               => 'UNMAPPED_SUBJECTS',
                    'message'              => 'Some course codes are not found in curriculum_subjects.',
                    'missing_course_codes' => array_values(array_unique($unmapped)),
                ], 422);
            }

            DB::commit();

            // ---- TRIGGER: auto-update Year, Academic_year, Major based on new grades ----
            try {
                // Pass BOTH AY label and normalized semester
                // e.g. $label = "2023-2024", $semester = "FIRST"
                StudentProgressService::syncFromGrades($studentId, $label, $semester);
            } catch (\Throwable $e) {
                \Log::warning('StudentProgressService failed after COG save', [
                    'student_id' => $studentId,
                    'error'      => $e->getMessage(),
                ]);
                // Huwag i-rollback dito – tapos na yung DB::commit() for grades
            }

            // SUCCESS: Return JSON with redirect information for frontend
            return response()->json([
                'ok'          => true,
                'inserted'    => $inserted,
                'updated'     => $updated,
                'image_saved' => $imageSaved,
                'redirect_url'=> route('student.grades.view'),
                'message'     => 'Grades uploaded successfully! Redirecting to view grades...',
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            \Log::error('Validation failed in storeFromCog', ['errors' => $e->errors()]);
            return response()->json([
                'ok'      => false,
                'message' => 'Validation failed: ' . implode(' ', array_map(function($fieldErrors) {
                    return implode(' ', $fieldErrors);
                }, $e->errors()))
            ], 422);
        } catch (\Throwable $e) {
            DB::rollBack();
            \Log::error('storeFromCog failed', ['err' => $e->getMessage()]);
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 500);
        }
    }


    /* -------------------------------- utils -------------------------------- */

    private function makeFilenameSafe($filename)
    {
        $ext  = pathinfo($filename, PATHINFO_EXTENSION);
        $name = pathinfo($filename, PATHINFO_FILENAME);
        $safe = preg_replace('/[^a-zA-Z0-9_-]/', '_', $name);
        $safe = substr($safe, 0, 100);
        return $safe . '.' . $ext;
    }

    private function cleanupTempFiles()
    {
        $tempDir = storage_path('app/public/temp/cog');
        if (File::exists($tempDir)) File::deleteDirectory($tempDir);

        $publicTempDir = public_path('temp/cog');
        if (File::exists($publicTempDir)) File::deleteDirectory($publicTempDir);
    }

    /** Build the single SOURCE line that starts grades_qr_output.txt */
    private function buildCogSourceLine(array $qrData): string
    {
        if (!empty($qrData['url']))  return 'SOURCE: QR_ONLY url=' . $qrData['url'];
        if (!empty($qrData['text'])) return 'SOURCE: QR_ONLY (raw) payload=' . substr($qrData['text'], 0, 120) . '…';
        return 'SOURCE: QR_EMPTY reason=NO_QR_FOUND_IN_FIRST_PAGE';
    }

    private function normalizePdfTextForDisplay(string $txt): string
    {
        if ($txt === '') return '';
        $txt = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $txt);
        $txt = str_replace(
            ['ﬁ', 'ﬂ', 'ﬀ', 'ﬃ', 'ﬄ', '—', '–', '•', '·', '', '●', '▪', '♦'],
            ['fi', 'fl', 'ff', 'ffi', 'ffl', '-', '-', '*', '.', '*', '*', '*', '*'],
            $txt
        );
        $txt = preg_replace('/[ \t]+/', ' ', $txt);
        $txt = str_replace("\r\n", "\n", $txt);
        $txt = preg_replace("/[ \t]+\n/", "\n", $txt);
        return trim($txt) . "\n";
    }

    /* ------------------------ helpers for DB save ------------------------ */

    private function makeShortRemarks(?string $rawGrade): string
    {
        $g = strtoupper(trim((string) $rawGrade));

        // PURE "INC" LANG → INC
        if ($g === 'INC') {
            return 'INC';
        }

        // DRP at W
        if ($g === 'DRP') {
            return 'DRP';
        }
        if ($g === 'W') {
            return 'W';
        }

        // May numeric part? (e.g. "3.00", "INC/3.00", "PASS/2.75")
        if (preg_match('/(\d+(?:\.\d+)?)/', $g)) {
            return 'PASSED';
        }

        return ''; // iba pang unexpected format
    }

    private function normalizeSemester(string $s): string
    {
        $key = strtolower(trim($s));
        $map = [
            '1'              => 'FIRST',
            '1st'            => 'FIRST',
            '1st sem'        => 'FIRST',
            'first semester' => 'FIRST',
            'first'          => 'FIRST',
            '2'              => 'SECOND',
            '2nd'            => 'SECOND',
            '2nd sem'        => 'SECOND',
            'second semester'=> 'SECOND',
            'second'         => 'SECOND',
            'midyear'        => 'MIDYEAR',
            'summer'         => 'MIDYEAR',
        ];
        $val = $map[$key] ?? strtoupper($s ?: 'FIRST'); // default to FIRST
        return mb_substr($val, 0, 10); // varchar(10) safety
    }
}
