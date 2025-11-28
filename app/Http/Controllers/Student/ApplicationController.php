<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use App\Models\Application;
use App\Models\Post;
use App\Models\StudentManage;
use App\Models\UserManage;
use App\Models\UserDesignation;
use setasign\Fpdi\Fpdi;
use Zxing\QrReader;

class ApplicationController extends Controller
{
    /* =========================
     * PAGE
     * ========================= */
    public function showApplication()
    {
        $studentId = session('Student_id');
        $today = now()->toDateString();

        // 🔍 Hanapin ACTIVE na Dean's Honor post TODAY
        $activePost = Post::where(function ($q) {
                $q->where('Title', 'LIKE', '%Dean%Honor%')
                ->orWhere('Announcement', 'LIKE', '%Dean%Honor%');
            })
            ->whereDate('Start_date', '<=', $today)
            ->whereDate('End_date', '>=', $today)
            ->orderByDesc('End_date')
            ->first();

        // ✅ Kung may activePost → OPEN, kung wala → CLOSED
        $isApplicationClosed = $activePost ? false : true;

        return view('student.application', [
            'post'               => $activePost,
            'isApplicationClosed'=> $isApplicationClosed,
        ]);
    }

    /* =========================
     * UTILITIES
     * ========================= */
    private function ensureDir(string $abs): void
    {
        File::ensureDirectoryExists($abs, 0755, true);
    }

    private function rankFromGwaNullable(?float $g): ?string
    {
        if ($g === null) return null;
        if ($g >= 1.0000 && $g <= 1.2500) return 'Tech Savant';
        if ($g <= 1.5000) return 'Tech Virtuoso';
        if ($g <= 1.7500) return 'Tech Prodigy';
        return 'Unranked';
    }

    private function latestGeneratedDeanForm(int $freshSecs = 900): ?string
    {
        $root = storage_path('app/public/generated');
        if (!is_dir($root)) return null;

        $latestFile = null;
        $latestTime = 0;
        foreach (File::files($root) as $f) {
            if (strtolower($f->getExtension()) !== 'pdf') continue;
            $mtime = $f->getMTime();
            if ($mtime > $latestTime) {
                $latestTime = $mtime;
                $latestFile = $f->getRealPath();
            }
        }
        return ($latestFile && (time() - $latestTime) <= $freshSecs) ? $latestFile : null;
    }

    /* =========================
     * UPLOAD METHODS
     * ========================= */
    public function uploadCor(Request $request)
    {
        try {
            $dir = storage_path('app/cor');
            $this->ensureDir($dir);

            if (!$request->hasFile('pdf')) {
                return response()->json([
                    'success' => false,
                    'message' => 'No PDF file uploaded'
                ], 400);
            }

            $file = $request->file('pdf');
            $filename = 'cor_upload.pdf';
            $file->move($dir, $filename);
            
            $pdfPath = $dir . '/' . $filename;
            
            // Convert first page to PNG for preview
            $pngPath = $dir . '/cor_upload.png';
            $converted = $this->renderPdfPage1ToPng($pdfPath, $pngPath, 150);
            
            $response = [
                'success' => true,
                'cor_pdf_path' => $pdfPath,
                'cor_pdf_url' => asset('storage/app/cor/cor_upload.pdf'),
                'message' => 'COR uploaded successfully'
            ];
            
            if ($converted) {
                $response['cor_png_path'] = $pngPath;
                $response['cor_png_url'] = asset('storage/app/cor/cor_upload.png');
            }
            
            return response()->json($response);
            
        } catch (\Exception $e) {
            Log::error('COR upload failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Upload failed: ' . $e->getMessage()
            ], 500);
        }
    }

    public function uploadCog(Request $request)
    {
        try {
            $dir = storage_path('app/cog');
            $this->ensureDir($dir);

            // Clear any existing files first
            $existingFiles = ['cog_upload.pdf', 'cog_upload.png'];
            foreach ($existingFiles as $file) {
                $path = $dir . '/' . $file;
                if (is_file($path)) {
                    @unlink($path);
                }
            }

            if ($request->hasFile('image')) {
                // Handle image upload
                $file = $request->file('image');
                $tempPath = $file->getRealPath();
                $finalPngPath = $dir . '/cog_upload.png';

                // Convert to 8-bit PNG if needed
                $imageInfo = @getimagesize($tempPath);
                if ($imageInfo && $imageInfo[2] === IMAGETYPE_PNG) {
                    $image = imagecreatefrompng($tempPath);
                    if ($image && imagecolorstotal($image) === 0) {
                        Log::info('Uploaded image is 16-bit PNG, converting to 8-bit');
                        $this->convertPngTo8Bit($tempPath, $finalPngPath);
                    } else {
                        // Copy as-is if already 8-bit
                        copy($tempPath, $finalPngPath);
                    }
                    if ($image) imagedestroy($image);
                } else {
                    // For non-PNG images or if detection fails, use original
                    $file->move($dir, 'cog_upload.png');
                }

                Log::info('COG image uploaded and processed', [
                    'filename' => 'cog_upload.png',
                    'size' => file_exists($finalPngPath) ? filesize($finalPngPath) : 0,
                    'path' => $finalPngPath
                ]);

                return response()->json([
                    'success' => true,
                    'cog_image_path' => $finalPngPath,
                    'cog_image_url' => asset('storage/app/cog/cog_upload.png'),
                    'message' => 'COG image uploaded successfully'
                ]);

            } elseif ($request->hasFile('pdf')) {
                // Handle PDF upload (your existing PDF code)
                $file = $request->file('pdf');
                $filename = 'cog_upload.pdf';
                $file->move($dir, $filename);
                
                $pdfPath = $dir . '/' . $filename;
                $pngPath = $dir . '/cog_upload.png';

                // Convert PDF to PNG
                $converted = $this->renderPdfPage1ToPng($pdfPath, $pngPath, 150);
                
                // Ensure the PNG is 8-bit
                if ($converted && file_exists($pngPath)) {
                    $this->ensure8BitPng($pngPath);
                }

                $response = [
                    'success' => true,
                    'cog_pdf_path' => $pdfPath,
                    'cog_pdf_url' => asset('storage/app/cog/cog_upload.pdf'),
                    'message' => 'COG PDF uploaded successfully'
                ];
                
                if ($converted) {
                    $response['cog_image_path'] = $pngPath;
                    $response['cog_image_url'] = asset('storage/app/cog/cog_upload.png');
                    $response['message'] = 'COG PDF uploaded and converted successfully';
                }
                
                return response()->json($response);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'No file uploaded'
                ], 400);
            }
            
        } catch (\Exception $e) {
            Log::error('COG upload failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Upload failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Ensure PNG is 8-bit
     */
    private function ensure8BitPng(string $pngPath): void
    {
        try {
            $image = imagecreatefrompng($pngPath);
            if ($image && imagecolorstotal($image) === 0) {
                Log::info('Converting generated PNG to 8-bit', ['path' => $pngPath]);
                $tempPath = $pngPath . '.tmp';
                if ($this->convertPngTo8Bit($pngPath, $tempPath)) {
                    rename($tempPath, $pngPath);
                }
            }
            if ($image) imagedestroy($image);
        } catch (\Exception $e) {
            Log::warning('Failed to ensure 8-bit PNG', ['path' => $pngPath, 'error' => $e->getMessage()]);
        }
    }

    /* =========================
     * PDF GENERATION
     * ========================= */
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

        // ============ Template ============
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

        // ============ Extract Section and Scholarship ============
        $section = '';
        $scholarship = '';

        // Try to extract from the provided data first
        $meta = (array)($data['meta'] ?? []);
        
        if (isset($meta['section'])) {
            $section = $meta['section'];
        } else {
            // Extract from COG output file
            $cogPath = storage_path('app/cog/cog_output.txt');
            if (file_exists($cogPath)) {
                $cogText = file_get_contents($cogPath);
                $section = $this->extractSectionFromCog($cogText);
                Log::info('Extracted section from COG file', ['section' => $section]);
            }
        }

        if (isset($meta['scholarship'])) {
            $scholarship = $meta['scholarship'];
        } else {
            // Extract from COR output file
            $corPath = storage_path('app/cor/cor_output.txt');
            if (file_exists($corPath)) {
                $corText = file_get_contents($corPath);
                $scholarship = $this->extractScholarshipFromCor($corText);
                Log::info('Extracted scholarship from COR file', ['scholarship' => $scholarship]);
            }
        }

        // Update the meta with extracted values
        $meta['section'] = $section ?: ($meta['section'] ?? '');
        $meta['scholarship'] = $scholarship ?: ($meta['scholarship'] ?? '');

        Log::info('Final section and scholarship for PDF', [
            'section' => $meta['section'],
            'scholarship' => $meta['scholarship']
        ]);

        // ============ Image Path Resolution ============
        $corPdf = storage_path('app/cor/cor_upload.pdf');
        $cogPdf = storage_path('app/cog/cog_upload.pdf');
        
        // Use provided paths or fallback to default locations
        $corPng = $data['cor_png_path'] ?? storage_path('app/cor/cor_upload.png');
        $cogPng = $data['cog_png_path'] ?? storage_path('app/cog/cog_upload.png');

        // Enhanced debug logging
        Log::info('=== IMAGE DEBUG START ===');
        Log::info('COR PDF exists: ' . (is_file($corPdf) ? 'YES' : 'NO'));
        Log::info('COG PDF exists: ' . (is_file($cogPdf) ? 'YES' : 'NO'));
        Log::info('COR PNG exists: ' . (is_file($corPng) ? 'YES' : 'NO'));
        Log::info('COG PNG exists: ' . (is_file($cogPng) ? 'YES' : 'NO'));

        // Convert PDFs to PNG if they don't exist
        if (!is_file($corPng) && is_file($corPdf)) {
            Log::info('Converting COR PDF to PNG...');
            $this->renderPdfPage1ToPng($corPdf, $corPng);
            Log::info('COR PNG after conversion: ' . (is_file($corPng) ? 'YES' : 'NO'));
        }

        if (!is_file($cogPng) && is_file($cogPdf)) {
            Log::info('Converting COG PDF to PNG...');
            $this->renderPdfPage1ToPng($cogPdf, $cogPng);
            Log::info('COG PNG after conversion: ' . (is_file($cogPng) ? 'YES' : 'NO'));
        }

        // Final check
        $finalCorPng = is_file($corPng) ? $corPng : null;
        $finalCogPng = is_file($cogPng) ? $cogPng : null;

        Log::info('Final COR PNG: ' . ($finalCorPng ?: 'MISSING'));
        Log::info('Final COG PNG: ' . ($finalCogPng ?: 'MISSING'));

        if ($finalCorPng) {
            $corInfo = @getimagesize($finalCorPng);
            Log::info('COR image info: ' . ($corInfo ? $corInfo[0] . 'x' . $corInfo[1] : 'INVALID'));
        }
        if ($finalCogPng) {
            $imageInfo = @getimagesize($finalCogPng);
            if ($imageInfo && $imageInfo[2] === IMAGETYPE_PNG) {
                $image = imagecreatefrompng($finalCogPng);
                if ($image && imagecolorstotal($image) === 0) {
                    Log::info('16-bit COG detected, converting to JPEG for placement');
                    $jpegPath = storage_path('app/temp/cog_temp.jpg');
                    $this->ensureDir(dirname($jpegPath));
                    
                    // Convert to JPEG
                    $jpegImage = imagecreatetruecolor($imageInfo[0], $imageInfo[1]);
                    $white = imagecolorallocate($jpegImage, 255, 255, 255);
                    imagefill($jpegImage, 0, 0, $white);
                    imagecopy($jpegImage, $image, 0, 0, 0, 0, $imageInfo[0], $imageInfo[1]);
                    imagejpeg($jpegImage, $jpegPath, 90);
                    imagedestroy($image);
                    imagedestroy($jpegImage);
                    
                    $finalCogPng = $jpegPath;
                }
            }
        }
        Log::info('=== IMAGE DEBUG END ===');

        // ============ Data Processing ============
        $rowsIn = (array)($data['rows'] ?? []);
        $totals = (array)($data['totals'] ?? []);

        $courseRows = [];
        foreach ($rowsIn as $r) {
            $code     = (string)($r['code']  ?? '');
            $title    = (string)($r['title'] ?? '');
            $units    = (float) ($r['units'] ?? 0);
            $gradeVal = $r['grade'] ?? 0;
            $grade    = is_numeric($gradeVal) ? (float)$gradeVal : (float)preg_replace('/[^\d.]+/','',(string)$gradeVal);
            $wg       = ($units > 0 && $grade > 0) ? $units * $grade : 0.0;

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

        // ============ Student ============
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

        // ============ Output paths ============
        $relOut = 'generated/deanslist_'.now()->format('Ymd_His').'_'.Str::random(5).'.pdf';
        $absOut = storage_path('app/public/'.$relOut);
        File::ensureDirectoryExists(dirname($absOut));

        try {
            $pdf = new Fpdi();
            $pageCount = $pdf->setSourceFile($template);

            // -------- Page 1 --------
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
            // Scholarship grant placed under Yr./Sec. and Track
            $pdf->SetXY(59  , 220  ); $pdf->Write(0, $scholarship);

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

            // -------- Page 2 --------
            if ($pageCount >= 2) {
                $tpl2  = $pdf->importPage(2);
                $size2 = $pdf->getTemplateSize($tpl2);
                $pdf->AddPage($size2['orientation'], [$size2['width'], $size2['height']]);
                $pdf->useTemplate($tpl2);

                // Attachment boxes
                $COR_BOX = ['x'=>26.0, 'y'=>18.0,  'w'=>167.0, 'h'=>118.0];
                $COG_BOX = ['x'=>25.0, 'y'=>200.0, 'w'=>170.0, 'h'=>118.0];

                // Apply shifts if needed
                $COR_SHIFT_Y = (float)($meta['cor_shift_y'] ?? -15.0);
                $COG_SHIFT_Y = (float)($meta['cog_shift_y'] ?? -89.0);
                $COR_BOX['y'] += $COR_SHIFT_Y;
                $COG_BOX['y'] += $COG_SHIFT_Y;

                Log::info('=== PAGE 2 IMAGE PLACEMENT ===');
                
                // Place COR image
                if ($finalCorPng) {
                    Log::info('Placing COR image: ' . $finalCorPng);
                    try {
                        $this->placeImageInBox($pdf, $finalCorPng, $COR_BOX);
                        Log::info('COR image placed successfully');
                    } catch (\Throwable $e) {
                        Log::error('COR image placement failed: ' . $e->getMessage());
                        $this->drawPlaceholderBox($pdf, $COR_BOX, 'COR Missing');
                    }
                } else {
                    Log::warning('COR image not available');
                    $this->drawPlaceholderBox($pdf, $COR_BOX, 'COR Missing');
                }
                
                // Place COG image
                if ($finalCogPng) {
                    Log::info('Placing COG image: ' . $finalCogPng);
                    try {
                        $this->placeImageInBox($pdf, $finalCogPng, $COG_BOX);
                        Log::info('COG image placed successfully');
                    } catch (\Throwable $e) {
                        Log::error('COG image placement failed: ' . $e->getMessage());
                        $this->drawPlaceholderBox($pdf, $COG_BOX, 'COG Missing');
                    }
                } else {
                    Log::warning('COG image not available');
                    $this->drawPlaceholderBox($pdf, $COG_BOX, 'COG Missing');
                }
                Log::info('=== END PAGE 2 IMAGE PLACEMENT ===');

                // Program Chair + Dean resolution
                try {
                    $CHAIR_ID = \App\Models\Designation::whereIn('Designation_name', [
                        'Program Chairperson','Department Chairperson','Chairperson'
                    ])->value('Designation_id') ?? 12;

                    $DEAN_ID = \App\Models\Designation::whereIn('Designation_name', [
                        'Dean','College Dean','Dean of College'
                    ])->value('Designation_id') ?? 10;

                    $campusId  = $curriculumAy->Campus_id  ?? null;
                    $collegeId = $curriculumAy->College_id ?? null;
                    $programId = $curriculumAy->Program_id ?? null;
                    $majorId   = $curriculumAy->Major_id   ?? null;

                    $compose = function ($u) {
                        if (!$u) return '';
                        $t = trim((string)($u->Title ?? ''));
                        $f = strtoupper((string)($u->First_name ?? ''));
                        $mi = $u->Middle_name ? strtoupper(substr((string)$u->Middle_name, 0, 1)).'.' : '';
                        $l = strtoupper((string)($u->Last_name ?? ''));
                        return trim($t.' '.$f.' '.($mi ? $mi.' ' : '').$l);
                    };

                    // Program Chair
                    $chair = \App\Models\UserDesignation::with('user')
                        ->where('designation_id', $CHAIR_ID)
                        ->where('college_id', $collegeId)
                        ->when($campusId,  fn($q)=>$q->where('campus_id',  $campusId))
                        ->orderByRaw('(CASE WHEN program_id = ? THEN 0 ELSE 1 END)', [$programId])
                        ->orderByRaw('(CASE WHEN major_id   = ? THEN 0 ELSE 1 END)', [$majorId])
                        ->first();

                    if (!$chair) {
                        $chair = \App\Models\UserDesignation::with('user')
                            ->where('designation_id', $CHAIR_ID)
                            ->where('college_id', $collegeId)
                            ->orderBy('UserDesignation_id','desc')
                            ->first();
                    }

                    $chairName     = $compose($chair?->user) ?: 'N/A';
                    $chairPosition = 'Department Chairperson, ' . ('ITE Program');

                    // Dean
                    $dean = \App\Models\UserDesignation::with('user')
                        ->where('designation_id', $DEAN_ID)
                        ->where('college_id', $collegeId)
                        ->when($campusId, fn($q)=>$q->where('campus_id', $campusId))
                        ->orderByRaw('(CASE WHEN program_id IS NULL THEN 0 ELSE 1 END)')
                        ->orderByRaw('(CASE WHEN major_id   IS NULL THEN 0 ELSE 1 END)')
                        ->first();

                    if (!$dean) {
                        $dean = \App\Models\UserDesignation::with('user')
                            ->where('designation_id', $DEAN_ID)
                            ->where('college_id', $collegeId)
                            ->orderBy('UserDesignation_id','desc')
                            ->first();
                    }

                    $deanName     = $compose($dean?->user) ?: 'N/A';
                    $deanPosition = 'Dean, ' . ($college ?: 'College');

                    // College stamps
                    $pdf->SetXY(138, 238);     $pdf->SetFont('Times', '', 10); $pdf->Write(0, $collegeAbbr ?: '???');
                    $pdf->SetXY(187.5, 242.2); $pdf->SetFont('Times', '', 10); $pdf->Write(0, $collegeAbbr ?: '???');
                    $pdf->SetXY(192, 246.8);   $pdf->SetFont('Times', '', 10); $pdf->Write(0, $collegeAbbr ?: '???');

                    $pdf->SetFont('ZapfDingbats', '', 8);
                    $pdf->SetXY(31, 238);     $pdf->Write(0, chr(52));  // Check icon ✓ before first college abbreviation
                    $pdf->SetXY(31, 242.2); $pdf->Write(0, chr(52));  // Check icon ✓ before second college abbreviation
                    $pdf->SetXY(31, 246.8);   $pdf->Write(0, chr(52));  // Check icon ✓ before third college abbreviation

                    // Student info - Section placed under student name on Page 2
                    $pdf->SetFont('Times', 'B', 12);
                    $pdf->SetXY(53, 263.5); $pdf->Write(0, "$first $middleInitial $last");
                    $pdf->SetFont('Times', '', 11);
                    $pdf->SetXY(53, 268);   $pdf->Write(0, $section ?: '');

                    // Chair signature
                    $pdf->SetFont('Times', 'B', 12);
                    $pdf->SetXY(53, 281.5); $pdf->Write(0, $chairName);
                    $pdf->SetFont('Times', '', 11);
                    $pdf->SetXY(53, 286.5); $pdf->Write(0, $chairPosition);

                    // Dean signature
                    $pdf->SetFont('Times', 'B', 12);
                    $pdf->SetXY(53, 301.5); $pdf->Write(0, $deanName);
                    $pdf->SetFont('Times', '', 11);
                    $pdf->SetXY(53, 306.5); $pdf->Write(0, $deanPosition);

                } catch (\Throwable $e) {
                    \Log::warning('Page 2 role fill failed', ['err'=>$e->getMessage()]);
                }
            }

            $pdf->Output($absOut, 'F');

            Log::info('PDF generated successfully: ' . $absOut);

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

    /* =========================
     * HELPER METHODS
     * ========================= */

    private function extractSectionFromCog(string $cogText): string
    {
        try {
            $lines = explode("\n", $cogText);
            $section = '';

            // Look for section pattern in course lines (e.g., "IT-2203")
            foreach ($lines as $line) {
                $line = trim($line);
                
                // Match course lines with section in parentheses
                if (preg_match('/\(([A-Z]+-\d+)\)/', $line, $matches)) {
                    $section = $matches[1];
                    Log::info('Found section in COG', ['section' => $section, 'line' => $line]);
                    break;
                }
                
                // Alternative: Look for section after course code pattern
                if (preg_match('/^\d+\s+[A-Z]+\s+\d+\s+.+?\s+\d+\s+[0-9.]+\s+([A-Z]+-\d+)\s+/', $line, $matches)) {
                    $section = $matches[1];
                    Log::info('Found section in COG (alternative)', ['section' => $section, 'line' => $line]);
                    break;
                }
            }

            // If not found, try to extract from the structured format
            if (empty($section)) {
                foreach ($lines as $line) {
                    // Match the format: "1 ES 101   Environmental Sciences                 3     1.50  IT-2203     MERCADO, ALBERT S."
                    if (preg_match('/\s+[A-Z]+-\d+\s+/', $line, $matches)) {
                        $parts = preg_split('/\s+/', trim($line));
                        foreach ($parts as $part) {
                            if (preg_match('/^[A-Z]+-\d+$/', $part)) {
                                $section = $part;
                                Log::info('Found section in COG (structured)', ['section' => $section, 'line' => $line]);
                                break 2;
                            }
                        }
                    }
                }
            }

            return $section ?: '';

        } catch (\Exception $e) {
            Log::warning('Failed to extract section from COG', ['error' => $e->getMessage()]);
            return '';
        }
    }

    /**
     * Extract scholarship grant from COR output text
     */
    private function extractScholarshipFromCor(string $corText): string
    {
        try {
            $lines = explode("\n", $corText);
            $scholarship = '';
            $inScholarshipSection = false;

            foreach ($lines as $line) {
                $line = trim($line);
                
                // Look for scholarship section header
                if (preg_match('/Scholarship/', $line) || preg_match('/Scholarship\/s:/', $line)) {
                    $inScholarshipSection = true;
                    Log::info('Found scholarship section header', ['line' => $line]);
                    continue;
                }
                
                // If in scholarship section, look for the grant text
                if ($inScholarshipSection) {
                    // Skip empty lines or lines that are clearly not scholarship text
                    if (empty($line) || 
                        preg_match('/Tuition Fee Discount/', $line) ||
                        preg_match('/Assessment Discount/', $line) ||
                        preg_match('/^\d+\.\d+$/', $line) ||
                        preg_match('/^[*]/', $line)) {
                        continue;
                    }
                    
                    // Look for the actual scholarship text
                    if (preg_match('/Higher Education Support Program/', $line) || 
                        preg_match('/Free Tuition/', $line) ||
                        preg_match('/[A-Za-z][A-Za-z\s]+:.*[A-Za-z]/', $line)) {
                        
                        $scholarship = trim($line);
                        Log::info('Found scholarship text', ['scholarship' => $scholarship, 'line' => $line]);
                        
                        // Clean up the scholarship text
                        $scholarship = preg_replace('/^\s*Scholarship\/s:\s*/', '', $scholarship);
                        $scholarship = preg_replace('/^\s*[*]\s*/', '', $scholarship);
                        break;
                    }
                }
                
                // If we hit the next section (ASSESSMENT), stop looking
                if ($inScholarshipSection && preg_match('/ASSESSMENT/', $line)) {
                    break;
                }
            }

            // If not found with section detection, try direct pattern matching
            if (empty($scholarship)) {
                foreach ($lines as $line) {
                    if (preg_match('/Higher Education Support Program.*Free Tuition.*\d{4}/', $line, $matches)) {
                        $scholarship = trim($matches[0]);
                        Log::info('Found scholarship (direct pattern)', ['scholarship' => $scholarship]);
                        break;
                    }
                }
            }

            return $scholarship ?: '';

        } catch (\Exception $e) {
            Log::warning('Failed to extract scholarship from COR', ['error' => $e->getMessage()]);
            return '';
        }
    }

    /**
     * Get section and scholarship data for PDF generation
     */
    public function getSectionAndScholarship()
    {
        try {
            $cogText = '';
            $corText = '';
            
            // Read COG output file
            $cogPath = storage_path('app/cog/cog_output.txt');
            if (file_exists($cogPath)) {
                $cogText = file_get_contents($cogPath);
            }
            
            // Read COR output file  
            $corPath = storage_path('app/cor/cor_output.txt');
            if (file_exists($corPath)) {
                $corText = file_get_contents($corPath);
            }
            
            $section = $this->extractSectionFromCog($cogText);
            $scholarship = $this->extractScholarshipFromCor($corText);
            
            Log::info('Extracted section and scholarship', [
                'section' => $section,
                'scholarship' => $scholarship,
                'cog_exists' => file_exists($cogPath),
                'cor_exists' => file_exists($corPath)
            ]);
            
            return response()->json([
                'success' => true,
                'section' => $section,
                'scholarship' => $scholarship
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to get section and scholarship', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to extract data: ' . $e->getMessage()
            ], 500);
        }
    }


    public function getCogAcademicInfo()
    {
        try {
            $cogPath = storage_path('app/cog/cog_output.txt');
            if (!file_exists($cogPath)) {
                return response()->json([
                    'success' => false,
                    'message' => 'COG output file not found'
                ], 404);
            }

            $cogText = file_get_contents($cogPath);
            $lines = explode("\n", $cogText);
            
            $academicInfo = [
                'academic_year' => '',
                'semester' => '',
                'year_level' => '',
                'program' => '',
                'section' => '',
                'fullname' => '',
                'srcode' => '',
                'college' => ''
            ];

            $inParsedDataSection = false;
            $inCoursesSection = false;

            foreach ($lines as $line) {
                $line = trim($line);
                
                // Check if we're entering the PARSED DATA section
                if (strpos($line, 'PARSED DATA:') !== false) {
                    $inParsedDataSection = true;
                    continue;
                }
                
                // Check if we're entering the COURSES section (end of PARSED DATA)
                if (strpos($line, 'COURSES:') !== false) {
                    $inParsedDataSection = false;
                    $inCoursesSection = true;
                    continue;
                }
                
                // Extract data from PARSED DATA section
                if ($inParsedDataSection && !empty($line)) {
                    // Extract Academic Year
                    if (preg_match('/Academic Year:\s*(.+)/i', $line, $matches)) {
                        $academicInfo['academic_year'] = trim($matches[1]);
                    }
                    
                    // Extract Semester
                    if (preg_match('/Semester:\s*(.+)/i', $line, $matches)) {
                        $academicInfo['semester'] = trim($matches[1]);
                    }
                    
                    // Extract Year Level
                    if (preg_match('/Year Level:\s*(.+)/i', $line, $matches)) {
                        $academicInfo['year_level'] = trim($matches[1]);
                    }
                    
                    // Extract Program
                    if (preg_match('/Program:\s*(.+)/i', $line, $matches)) {
                        $academicInfo['program'] = trim($matches[1]);
                    }
                    
                    // Extract Fullname
                    if (preg_match('/Fullname:\s*(.+)/i', $line, $matches)) {
                        $academicInfo['fullname'] = trim($matches[1]);
                    }
                    
                    // Extract SRCODE
                    if (preg_match('/SRCODE:\s*(.+)/i', $line, $matches)) {
                        $academicInfo['srcode'] = trim($matches[1]);
                    }
                    
                    // Extract College
                    if (preg_match('/College:\s*(.+)/i', $line, $matches)) {
                        $academicInfo['college'] = trim($matches[1]);
                    }
                }
                
                // Extract section from course lines in COURSES section
                if ($inCoursesSection && empty($academicInfo['section'])) {
                    // Look for section pattern in course lines (e.g., "IT-NT-3201")
                    if (preg_match('/\|\s*([A-Z]+-[A-Z]+-\d+)\s*\|/', $line, $matches)) {
                        $academicInfo['section'] = trim($matches[1]);
                        break; // Found section, no need to continue
                    }
                }
                
                // Alternative: Extract section from RAW EXTRACTED TEXT format
                if (strpos($line, 'RAW EXTRACTED TEXT:') !== false) {
                    $inCoursesSection = false;
                }
                
                // If we have all the main info, we can break early
                if (!empty($academicInfo['academic_year']) && 
                    !empty($academicInfo['semester']) && 
                    !empty($academicInfo['section'])) {
                    break;
                }
            }

            // If section not found in COURSES section, try alternative patterns
            if (empty($academicInfo['section'])) {
                foreach ($lines as $line) {
                    // Look for section in the tab-separated format
                    if (preg_match('/\d+\s+[A-Z]+\s+\d+\s+.+?\s+\d+\s+[0-9.]+\s+([A-Z]+-[A-Z]+-\d+)\s+/', $line, $matches)) {
                        $academicInfo['section'] = trim($matches[1]);
                        break;
                    }
                    
                    // Look for section in parentheses (alternative format)
                    if (preg_match('/\(([A-Z]+-[A-Z]+-\d+)\)/', $line, $matches)) {
                        $academicInfo['section'] = trim($matches[1]);
                        break;
                    }
                }
            }

            Log::info('Extracted academic info from COG', $academicInfo);

            return response()->json([
                'success' => true,
                'academic_info' => $academicInfo
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get COG academic info', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to extract academic info: ' . $e->getMessage()
            ], 500);
        }
    }

    private function placeImageInBox($pdf, $imagePath, $box)
    {
        if (!is_file($imagePath)) {
            throw new \Exception("Image file not found: " . $imagePath);
        }

        $imageInfo = @getimagesize($imagePath);
        if (!$imageInfo) {
            throw new \Exception("Invalid image file: " . $imagePath);
        }

        list($imgW, $imgH) = $imageInfo;
        
        // Check if it's a 16-bit PNG
        $is16BitPng = false;
        if ($imageInfo[2] === IMAGETYPE_PNG) {
            $image = imagecreatefrompng($imagePath);
            if ($image) {
                // Check color depth by examining the image
                $is16BitPng = (imagecolorstotal($image) === 0); // 16-bit PNGs have 0 in colorstotal
                imagedestroy($image);
                
                if ($is16BitPng) {
                    Log::warning('16-bit PNG detected, converting to 8-bit', ['path' => $imagePath]);
                    
                    // Convert to 8-bit
                    $tempPath = storage_path('app/temp/8bit_' . basename($imagePath));
                    File::ensureDirectoryExists(dirname($tempPath));
                    
                    if ($this->convertPngTo8Bit($imagePath, $tempPath)) {
                        $imagePath = $tempPath; // Use the converted image
                        $imageInfo = getimagesize($imagePath);
                        list($imgW, $imgH) = $imageInfo;
                        Log::info('Using converted 8-bit PNG', ['path' => $tempPath]);
                    } else {
                        Log::error('Failed to convert 16-bit PNG, trying direct placement');
                        // Continue with original image - might work with some PDF libraries
                    }
                }
            }
        }

        // Calculate scale to fit within box while maintaining aspect ratio
        $scale = min($box['w'] / $imgW, $box['h'] / $imgH);
        $newW = $imgW * $scale;
        $newH = $imgH * $scale;
        
        // Center the image in the box
        $x = $box['x'] + ($box['w'] - $newW) / 2;
        $y = $box['y'] + ($box['h'] - $newH) / 2;
        
        try {
            $pdf->Image($imagePath, $x, $y, $newW, $newH);
            Log::info('Image placed successfully', [
                'path' => $imagePath,
                'box' => $box,
                'position' => ['x' => $x, 'y' => $y, 'w' => $newW, 'h' => $newH]
            ]);
            
            // Clean up temp file if we created one
            if (isset($tempPath) && file_exists($tempPath)) {
                @unlink($tempPath);
            }
            
        } catch (\Exception $e) {
            // If placement fails, try alternative method
            Log::warning('Standard image placement failed, trying alternative', [
                'error' => $e->getMessage(),
                'path' => $imagePath
            ]);
            
            $this->placeImageAlternative($pdf, $imagePath, $box);
            
            // Clean up temp file if we created one
            if (isset($tempPath) && file_exists($tempPath)) {
                @unlink($tempPath);
            }
        }
    }

    private function placeImageAlternative($pdf, $imagePath, $box)
    {
        try {
            // Method 1: Try without dimensions (let FPDF handle it)
            $pdf->Image($imagePath, $box['x'], $box['y'], $box['w'], $box['h']);
            Log::info('Alternative placement method 1 succeeded');
        } catch (\Exception $e1) {
            try {
                // Method 2: Convert to JPEG temporarily
                $jpegPath = $this->convertPngToJpeg($imagePath);
                if ($jpegPath) {
                    $pdf->Image($jpegPath, $box['x'], $box['y'], $box['w'], $box['h']);
                    @unlink($jpegPath); // Clean up temp file
                    Log::info('Alternative placement method 2 (JPEG conversion) succeeded');
                } else {
                    throw new \Exception('JPEG conversion failed');
                }
            } catch (\Exception $e2) {
                Log::error('All image placement methods failed', [
                    'original_error' => $e1->getMessage(),
                    'jpeg_error' => $e2->getMessage()
                ]);
                throw new \Exception("Image placement failed: " . $e1->getMessage());
            }
        }
    }

    private function convertPngToJpeg(string $pngPath): ?string
    {
        try {
            $image = imagecreatefrompng($pngPath);
            if (!$image) {
                return null;
            }

            $jpegPath = storage_path('app/temp/' . uniqid() . '.jpg');
            File::ensureDirectoryExists(dirname($jpegPath));

            // Create white background
            $width = imagesx($image);
            $height = imagesy($image);
            $jpegImage = imagecreatetruecolor($width, $height);
            $white = imagecolorallocate($jpegImage, 255, 255, 255);
            imagefill($jpegImage, 0, 0, $white);

            // Copy PNG onto white background
            imagecopy($jpegImage, $image, 0, 0, 0, 0, $width, $height);

            // Save as JPEG
            imagejpeg($jpegImage, $jpegPath, 90);

            // Clean up
            imagedestroy($image);
            imagedestroy($jpegImage);

            return file_exists($jpegPath) ? $jpegPath : null;

        } catch (\Exception $e) {
            Log::warning('PNG to JPEG conversion failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    private function drawPlaceholderBox($pdf, $box, $text)
    {
        $pdf->SetDrawColor(200, 200, 200);
        $pdf->Rect($box['x'], $box['y'], $box['w'], $box['h']);
        
        $pdf->SetTextColor(150, 150, 150);
        $pdf->SetFont('Arial', 'I', 10);
        $textWidth = $pdf->GetStringWidth($text);
        $x = $box['x'] + ($box['w'] - $textWidth) / 2;
        $y = $box['y'] + ($box['h'] / 2);
        $pdf->SetXY($x, $y);
        $pdf->Write(0, $text);
    }

    private function renderPdfPage1ToPng(string $pdfAbs, string $pngAbs, int $dpi = 150): bool
    {
        try {
            if (class_exists(\Imagick::class)) {
                File::ensureDirectoryExists(dirname($pngAbs));
                
                $im = new \Imagick();
                $im->setResolution($dpi, $dpi);
                $im->readImage($pdfAbs . '[0]');
                $im->setImageFormat('png');
                $im->setImageCompressionQuality(95);
                $im->setImageBackgroundColor('white');
                $im = $im->mergeImageLayers(\Imagick::LAYERMETHOD_FLATTEN);
                
                $im->writeImage($pngAbs);
                $im->clear();
                $im->destroy();
                
                return is_file($pngAbs);
            }
            
            // Fallback: return true if PDF exists (we'll handle conversion elsewhere)
            if (is_file($pdfAbs)) {
                return true;
            }
            
        } catch (\Throwable $e) {
            Log::warning('PDF to PNG conversion failed', [
                'pdf' => $pdfAbs,
                'error' => $e->getMessage()
            ]);
        }
        
        return false;
    }

    /* =========================
     * EXISTING APPLICATION METHODS
     * ========================= */
    public function saveCorOutput(Request $r)
    {
        $dir = storage_path('app/cor');
        $this->ensureDir($dir);

        $pdfAbs = $dir . '/cor_upload.pdf';
        $sha    = is_file($pdfAbs) ? (@hash_file('sha256', $pdfAbs) ?: '') : '';
        $srcHdr = 'SOURCE: OCR_FROM_PDF'
                . ' sha256=' . $sha
                . ' path='   . $pdfAbs;

        $extracted = is_file($pdfAbs) ? $this->extractTextFromPdfForCor($pdfAbs) : '';

        $clientRaw = (string) $r->input('raw_text', '');
        $body = $extracted !== '' ? $extracted : $clientRaw;

        if (stripos($body, '----- OCR TEXT') === false) {
            $body = "----- OCR TEXT (raw) -----\n" . ltrim($body);
        }

        $final = $srcHdr . "\n" . $body;

        $tmp = $dir . '/.cor_output.tmp';
        \File::put($tmp, $final);
        @rename($tmp, $dir . '/cor_output.txt');

        try { \File::put($dir . '/_cor_output_raw.txt', $clientRaw); } catch (\Throwable $e) {}

        \Log::info('COR output written', [
            'pdf_exists' => is_file($pdfAbs),
            'sha_present'=> $sha !== '',
            'used'       => $extracted !== '' ? 'pdftotext_or_smalot' : 'client_raw',
            'out'        => $dir . '/cor_output.txt',
        ]);

        return response()->json(['ok' => true]);
    }

    public function saveCogOutput(Request $r)
    {
        try {
            $dir = storage_path('app/cog');
            $this->ensureDir($dir);

            $meta    = (array) $r->input('meta', []);
            $pdfTxt  = (string) $r->input('pdf_text', '');
            $ocrAll  = (string) $r->input('ocr_full', '');
            $qrRaw   = (string) $r->input('qr_raw', '');
            $qrUrlIn = (string) $r->input('qr_url', '');
            $pdfAbs  = (string) $r->input('pdf_abs', '');

            // QR decoding logic
            if ($r->hasFile('qr_image')) {
                try {
                    $tmp = $r->file('qr_image')->store('qr_tmp', 'local');
                    $abs = storage_path('app/'.$tmp);

                    $qrReader = new \Zxing\QrReader($abs);
                    $decoded  = trim((string) $qrReader->text());

                    @unlink($abs);

                    if ($decoded !== '') {
                        if (preg_match('#^https?://#i', $decoded)) {
                            $qrUrlIn = $decoded;
                        } else {
                            $qrRaw = $decoded;
                        }
                        try { File::put($dir.'/_debug_qr_decoded_from_image.txt', $decoded."\n"); } catch (\Throwable $e) {}
                    }
                } catch (\Throwable $e) {
                    Log::warning('QR image decode failed', ['err' => $e->getMessage()]);
                }
            }

            // Auto-decode QR
            if ($qrRaw === '' && $qrUrlIn === '') {
                $auto = $this->tryAutoDecodeQrFromPdfOrPng($pdfAbs ?: null);
                if ($auto['decoded'] !== '') {
                    if (preg_match('#^https?://#i', $auto['decoded'])) $qrUrlIn = $auto['decoded'];
                    else $qrRaw = $auto['decoded'];
                    Log::info('Auto QR decoded', ['source'=>$auto['source'], 'aux'=>$auto['aux']]);
                } else {
                    Log::warning('Auto QR decode failed (no QR found in png/pdf).');
                }
            }

            $qrUrl = $this->normalizeQrUrl($qrUrlIn);

            // QR processing
            $rowsQR = [];
            $metaQR = [];

            if ($qrRaw !== '') {
                if ($parsed = $this->parseQrPayload($qrRaw)) {
                    $rowsQR = (array) ($parsed['grades'] ?? []);
                    $metaQR = (array) ($parsed['meta'] ?? []);
                }
            }

            if (empty($rowsQR) && $qrUrl !== '') {
                $qrText = $this->fetchQrText($qrUrl);
                if ($qrText !== '') {
                    try { File::put($dir.'/_debug_qr_fetch.txt', $qrText); } catch (\Throwable $e) { }
                    $parsed = $this->parseQrGradesFromText($qrText);
                    $rowsQR = (array) ($parsed['rows'] ?? []);
                    $metaQR = (array) ($parsed['meta'] ?? []);
                }
            }

            if (!empty($metaQR)) {
                $meta = $metaQR + $meta;
            }

            // OCR processing
            $wroteOcr = $this->writeCogOcrFromPdfIfPossible($pdfAbs ?: null);
            if (!$wroteOcr) {
                if     ($pdfTxt !== '') File::put($dir.'/cog_ocr_output.txt', "SOURCE: OCR_FROM_PDF\n".$pdfTxt);
                elseif ($ocrAll !== '') File::put($dir.'/cog_ocr_output.txt', "SOURCE: OCR_FROM_PDF\n".$ocrAll);
                elseif (!is_file($dir.'/cog_ocr_output.txt')) File::put($dir.'/cog_ocr_output.txt', "SOURCE: OCR_FROM_PDF\n");
            }

            $rowsOCR = [];
            $ocrTxt  = (string) @File::get($dir.'/cog_ocr_output.txt');
            if ($ocrTxt !== '') {
                $parsedOcr = $this->parseQrGradesFromText($ocrTxt);
                $rowsOCR   = (array) ($parsedOcr['rows'] ?? []);
                if (empty($rowsQR) && !empty($parsedOcr['meta'])) {
                    $meta = array_filter($parsedOcr['meta']) + $meta;
                }
            }

            // Write output files
            $metaQRFinal = $this->sanitizeMeta(($metaQR ?: []) + $meta);
            $qrEmptyReason = '';
            if (empty($rowsQR)) {
                $qrEmptyReason = $qrUrl ? 'FETCH_PARSE_FAILED' : 'NO_QR_FOUND_IN_IMAGE_OR_PDF';
            }
            $qrHeader = !empty($rowsQR)
                ? "SOURCE: QR_ONLY url={$qrUrl}\n"
                : "SOURCE: QR_EMPTY url={$qrUrl} reason={$qrEmptyReason}\n";

            $asciiQR = $this->buildAsciiGradesTextExact($qrUrl, $metaQRFinal, $rowsQR);

            File::put($dir.'/cog_qr_output.txt', $qrHeader.$asciiQR);
            File::put($dir.'/cog_output.txt',     $qrHeader.$asciiQR);

            File::put($dir.'/parse_qr_grades_only.txt',  $this->buildGradesOnlyTable($rowsQR));
            File::put($dir.'/parse_ocr_grades_only.txt', $this->buildGradesOnlyTable($rowsOCR));

            $this->writeGradesMismatchReport($rowsQR, $rowsOCR);

            return response()->json([
                'ok' => true,
                'paths' => [
                    'cog_output'        => 'storage/app/cog/cog_output.txt',
                    'cog_qr_output'     => 'storage/app/cog/cog_qr_output.txt',
                    'cog_ocr_output'    => 'storage/app/cog/cog_ocr_output.txt',
                    'parse_qr_grades'   => 'storage/app/cog/parse_qr_grades_only.txt',
                    'parse_ocr_grades'  => 'storage/app/cog/parse_ocr_grades_only.txt',
                    '_debug_qr_fetch'   => 'storage/app/cog/_debug_qr_fetch.txt',
                    '_debug_qr_decoded' => 'storage/app/cog/_debug_qr_decoded_from_image.txt',
                    'mismatch_report'   => 'storage/app/cog/grades_mismatches.txt',
                ],
                'qr_rows'  => count($rowsQR),
                'ocr_rows' => count($rowsOCR),
                'source'   => !empty($rowsQR) ? 'QR_ONLY' : 'QR_EMPTY',
            ]);

        } catch (\Throwable $e) {
            Log::error('saveCogOutput failed', [
                'err'  => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace'=> $e->getTraceAsString(),
            ]);
            return response()->json([
                'ok' => false,
                'message' => 'saveCogOutput error (logged).',
            ], 200);
        }
    }

    public function getCogOutputFile()
    {
        $abs = storage_path('app/cog/cog_output.txt');
        if (!is_file($abs)) return response('')->header('Content-Type', 'text/plain');
        return response(File::get($abs))->header('Content-Type', 'text/plain');
    }

    public function getCogOcrOutputFile()
    {
        $abs = storage_path('app/cog/cog_ocr_output.txt');
        if (!is_file($abs)) return response('')->header('Content-Type', 'text/plain');
        return response(File::get($abs))->header('Content-Type', 'text/plain');
    }

    public function getParseQrGrades()
    {
        $abs = storage_path('app/cog/parse_qr_grades_only.txt');
        if (!is_file($abs)) return response('')->header('Content-Type', 'text/plain');
        return response(File::get($abs))->header('Content-Type', 'text/plain');
    }

    public function getParseOcrGrades()
    {
        $abs = storage_path('app/cog/parse_ocr_grades_only.txt');
        if (!is_file($abs)) return response('')->header('Content-Type', 'text/plain');
        return response(File::get($abs))->header('Content-Type', 'text/plain');
    }

    public function validateGrades(Request $r)
    {
        $qr  = $this->readGradesOnly(storage_path('app/cog/parse_qr_grades_only.txt'));
        $ocr = $this->readGradesOnly(storage_path('app/cog/parse_ocr_grades_only.txt'));

        if (!$qr && !$ocr) return response()->json(['status' => 'fail', 'message' => 'No parsed files available']);

        $N = max(count($qr), count($ocr));
        $mismatches = [];
        for ($i = 0; $i < $N; $i++) {
            $a = $qr[$i]  ?? '';
            $b = $ocr[$i] ?? '';
            if ($a !== $b) $mismatches[] = ['index' => $i + 1, 'qr' => $a ?: '—', 'ocr' => $b ?: '—'];
        }

        if ($mismatches) return response()->json(['status' => 'fail', 'mismatches' => $mismatches]);
        return response()->json(['status' => 'success', 'message' => 'Grades match']);
    }

    public function resolveQr(Request $r)
    {
        $raw = (string) $r->input('qr_raw', '');
        $url = (string) $r->input('qr_url', '');

        $payload = $this->parseQrPayload($raw);
        if ($payload && !empty($payload['grades'])) {
            $payload['qr_url'] = $url ?: ($this->extractQrUrlFromPayload($raw) ?? '');
            return response()->json($payload);
        }

        $displayUrl = $url ?: ($this->extractQrUrlFromPayload($raw) ?? '');
        return response()->json(['error' => 'No grades table found', 'qr_url' => $displayUrl], 422);
    }

    /**
     * Check if the extracted academic year/semester matches any active post
     * and return the matching Post_id
     */
    public function validateApplicationPeriod(Request $request)
    {
        try {
            Log::info('=== Starting Application Period Validation ===');

            // Try to get academic info from COG file first
            $cogPath = storage_path('app/cog/cog_output.txt');
            if (!file_exists($cogPath)) {
                Log::warning('COG file not found at path: ' . $cogPath);
                return response()->json([
                    'valid' => false,
                    'message' => 'COG file not found. Please upload your Certificate of Grades first.',
                    'match' => false,
                    'post_id' => null
                ]);
            }

            Log::info('COG file found, reading content...');
            $cogText = file_get_contents($cogPath);
            
            Log::info('COG file content length: ' . strlen($cogText));
            
            // Extract academic info directly from COG text
            $academicInfo = $this->extractAcademicInfoFromCog($cogText);
            
            Log::info('Extraction result:', $academicInfo);

            $academicYear = $academicInfo['academic_year'] ?? '';
            $semester = $academicInfo['semester'] ?? '';

            Log::info('Final academic values:', [
                'academic_year' => $academicYear,
                'semester' => $semester
            ]);

            // If still empty, return detailed error
            if (empty($academicYear) || empty($semester)) {
                Log::error('Failed to extract academic info from COG', [
                    'cog_preview' => substr($cogText, 0, 500),
                    'all_extracted_info' => $academicInfo
                ]);
                
                return response()->json([
                    'valid' => false,
                    'message' => 'Could not extract Academic Year and Semester from your COG. Please ensure you have uploaded a valid Certificate of Grades.',
                    'match' => false,
                    'post_id' => null,
                    'debug' => [
                        'extracted_data' => $academicInfo,
                        'cog_preview' => substr($cogText, 0, 300)
                    ]
                ]);
            }
            
            $today = now()->toDateString();
            
            // Normalize semester format for comparison
            $normalizedSemester = $this->normalizeSemesterForComparison($semester);
            
            Log::info('Searching for matching post in database', [
                'academic_year' => $academicYear,
                'original_semester' => $semester,
                'normalized_semester' => $normalizedSemester,
                'today' => $today
            ]);

            // SIMPLE DATABASE QUERY - Direct match
            $matchingPost = Post::where(function ($q) {
                    $q->where('Title', 'LIKE', '%Dean%Honor%')
                    ->orWhere('Announcement', 'LIKE', '%Dean%Honor%');
                })
                ->where('Academic_year', $academicYear)
                ->where('Semester', $normalizedSemester)
                ->whereDate('Start_date', '<=', $today)
                ->whereDate('End_date', '>=', $today)
                ->first();

            $isValid = !is_null($matchingPost);
            
            Log::info('Validation result:', [
                'match_found' => $isValid,
                'post_id' => $matchingPost ? $matchingPost->Post_id : null,
                'post_academic_year' => $matchingPost ? $matchingPost->Academic_year : null,
                'post_semester' => $matchingPost ? $matchingPost->Semester : null
            ]);

            if ($isValid) {
                return response()->json([
                    'valid' => true,
                    'message' => 'Application period matches active Dean\'s List posting.',
                    'match' => true,
                    'post_id' => $matchingPost->Post_id,
                    'post' => [
                        'id' => $matchingPost->Post_id,
                        'title' => $matchingPost->Title,
                        'academic_year' => $matchingPost->Academic_year,
                        'semester' => $matchingPost->Semester,
                        'start_date' => $matchingPost->Start_date,
                        'end_date' => $matchingPost->End_date
                    ]
                ]);
            } else {
                // Get all active posts for debugging
                $activePosts = Post::where(function ($q) {
                        $q->where('Title', 'LIKE', '%Dean%Honor%')
                        ->orWhere('Announcement', 'LIKE', '%Dean%Honor%');
                    })
                    ->whereDate('Start_date', '<=', $today)
                    ->whereDate('End_date', '>=', $today)
                    ->get();

                Log::warning('No matching post found. Available posts:', [
                    'searched_academic_year' => $academicYear,
                    'searched_semester' => $semester,
                    'available_posts' => $activePosts->pluck('Academic_year', 'Semester', 'Post_id')
                ]);

                return response()->json([
                    'valid' => false,
                    'message' => "No active Dean's List application period found for {$normalizedSemester} Semester, AY {$academicYear}.",
                    'match' => false,
                    'post_id' => null,
                    'debug' => [
                        'extracted_academic_year' => $academicYear,
                        'extracted_semester' => $semester,
                        'available_posts' => $activePosts->toArray()
                    ]
                ]);
            }
            
        } catch (\Exception $e) {
            Log::error('Application period validation failed:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'valid' => false,
                'message' => 'Validation failed: ' . $e->getMessage(),
                'match' => false,
                'post_id' => null
            ], 500);
        }
    }

    /**
     * Extract academic information from COG text - SIMPLIFIED VERSION
     */
    private function extractAcademicInfoFromCog(string $cogText): array
    {
        $academicInfo = [
            'academic_year' => '',
            'semester' => '',
            'year_level' => '',
            'program' => '',
            'section' => '',
            'fullname' => '',
            'srcode' => '',
            'college' => ''
        ];

        // Direct pattern matching from the entire text
        if (preg_match('/Academic Year:\s*([0-9]{4}-[0-9]{4})/i', $cogText, $matches)) {
            $academicInfo['academic_year'] = trim($matches[1]);
        }
        
        if (preg_match('/Semester:\s*(FIRST|SECOND|SUMMER)/i', $cogText, $matches)) {
            $academicInfo['semester'] = trim($matches[1]);
        }
        
        if (preg_match('/Year Level:\s*(.+)/i', $cogText, $matches)) {
            $academicInfo['year_level'] = trim($matches[1]);
        }
        
        if (preg_match('/Program:\s*(.+)/i', $cogText, $matches)) {
            $academicInfo['program'] = trim($matches[1]);
        }
        
        if (preg_match('/Fullname:\s*(.+)/i', $cogText, $matches)) {
            $academicInfo['fullname'] = trim($matches[1]);
        }
        
        if (preg_match('/SRCODE:\s*(.+)/i', $cogText, $matches)) {
            $academicInfo['srcode'] = trim($matches[1]);
        }
        
        if (preg_match('/College:\s*(.+)/i', $cogText, $matches)) {
            $academicInfo['college'] = trim($matches[1]);
        }

        // Extract section from course lines
        if (preg_match('/\|\s*([A-Z]+-[A-Z]+-\d+)\s*\|/', $cogText, $matches)) {
            $academicInfo['section'] = trim($matches[1]);
        }

        return $academicInfo;
    }

    /**
     * Normalize semester for database comparison
     */
    private function normalizeSemesterForComparison(string $semester): string
    {
        $semester = strtoupper(trim($semester));
        
        $mapping = [
            'SECOND' => 'Second',
            'FIRST' => 'First', 
            'SUMMER' => 'Summer'
        ];
        
        return $mapping[$semester] ?? $semester;
    }

    public function store(Request $request)
    {
        return $this->submitApplication($request);
    }

    public function submitApplication(Request $request)
    {
        $studentId = session('Student_id');
        if (!$studentId) return response()->json(['ok' => false, 'message' => 'Student not logged in.'], 403);

        // Get the Post_id from the request (passed from frontend validation)
        $postId = $request->input('post_id');
        
        if (!$postId) {
            return response()->json([
                'ok' => false, 
                'message' => 'No valid application period found. Please complete validation first.'
            ], 422);
        }

        $isMultipart = $request->hasFile('file');
        if ($isMultipart) {
            $data = $request->validate([
                'type'      => 'required|string|max:100',
                'file_name' => 'required|string|max:255',
                'gwa'       => 'nullable|numeric',
                'rank'      => 'nullable|string|max:100',
                'status'    => 'nullable|string|max:50',
                'context'   => 'nullable',
                'file'      => 'required|file|mimes:pdf|max:10240',
                'post_id'   => 'required|integer|exists:post,Post_id'
            ]);
            $fallbackBinary = file_get_contents($request->file('file')->getRealPath());
            $fallbackName   = $data['file_name'];
        } else {
            $data = $request->validate([
                'type'             => 'required|string|max:100',
                'file_name'        => 'required|string|max:255',
                'file_data_base64' => 'required|string',
                'gwa'              => 'nullable|numeric',
                'rank'             => 'nullable|string|max:100',
                'status'           => 'nullable|string|max:50',
                'context'          => 'nullable',
                'post_id'          => 'required|integer|exists:post,Post_id'
            ]);
            $b64 = $data['file_data_base64'];
            if (str_starts_with($b64, 'data:')) {
                $comma = strpos($b64, ',');
                $b64   = $comma !== false ? substr($b64, $comma + 1) : $b64;
            }
            $fallbackBinary = base64_decode($b64, true);
            if ($fallbackBinary === false || $fallbackBinary === null) {
                return response()->json(['ok' => false, 'message' => 'Invalid file_data_base64.'], 422);
            }
            if (strlen($fallbackBinary) > 10 * 1024 * 1024) {
                return response()->json(['ok' => false, 'message' => 'PDF is too large. Max 10MB.'], 413);
            }
            $fallbackName = $data['file_name'];
        }

        $type   = $data['type'] ?? 'DeanLister';
        $status = 'For Evaluation';

        $gwaInput = $data['gwa'] ?? null;
        $gwaNum   = is_numeric($gwaInput) ? (float)$gwaInput : null;
        $rankIn   = $data['rank'] ?? null;

        if ($gwaNum === null && isset($data['context'])) {
            try {
                $ctx   = is_string($data['context']) ? json_decode($data['context'], true) : $data['context'];
                $ctxGwa = data_get($ctx, 'totals.gwa');
                if (is_numeric($ctxGwa)) $gwaNum = (float)$ctxGwa;
            } catch (\Throwable $e) { }
        }

        $rankFinal   = $rankIn ?: ($this->rankFromGwaNullable($gwaNum) ?? 'Unranked');
        $gwaToStore  = $gwaNum ?? 0.0;
        $rankToStore = $rankFinal ?: 'Unranked';

        $storeBinary = $fallbackBinary;
        $storeName   = $fallbackName;

        try {
            $ctx = isset($data['context'])
                ? (is_string($data['context']) ? json_decode($data['context'], true) : $data['context'])
                : null;

            $explicitRel = is_array($ctx) ? ($ctx['generated_rel'] ?? null) : null;
            $explicitAbs = $explicitRel ? storage_path('app/public/' . ltrim($explicitRel, '/')) : null;

            $generatedAbs = null;
            if ($explicitAbs && is_file($explicitAbs)) $generatedAbs = $explicitAbs;
            else $generatedAbs = $this->latestGeneratedDeanForm(15 * 60);

            if ($generatedAbs && is_file($generatedAbs)) {
                $storeBinary = (string) file_get_contents($generatedAbs);
                $storeName   = "Application for Dean's Lister.pdf";
            }
        } catch (\Throwable $e) {
            Log::warning('Using fallback file (could not read generated form)', ['err' => $e->getMessage()]);
        }

        try {
            // Get student data to find their college
            $student = StudentManage::with([
                'curriculum.curriculumAy.college',
            ])->findOrFail($studentId);

            $collegeId = $student->curriculum?->curriculumAy?->College_id ?? null;

            // Find Program Chairperson for the student's college
            $programChair = null;
            if ($collegeId) {
                $CHAIR_ID = \App\Models\Designation::whereIn('Designation_name', [
                    'Program Chairperson','Department Chairperson','Chairperson'
                ])->value('Designation_id') ?? 12;

                $programChair = \App\Models\UserDesignation::with('user')
                    ->where('designation_id', $CHAIR_ID)
                    ->where('college_id', $collegeId)
                    ->first();
            }

            $app = new Application();
            $app->Student_id = $studentId;
            $app->Post_id    = $postId; // Store the matching Post_id
            $app->Type       = $type;
            $app->File_name  = $storeName;
            $app->File_data  = $storeBinary;
            $app->GWA        = $gwaToStore;
            $app->Rank       = $rankToStore;
            $app->Status     = $status;
            $app->save();

            // Insert into application_recipient for the Program Chairperson
            if ($programChair && $programChair->user) {
                \App\Models\ApplicationRecipient::create([
                    'Application_id' => $app->Application_id,
                    'User_id' => $programChair->user->User_id,
                    'is_read' => false,
                ]);
                
                Log::info('Application recipient added', [
                    'application_id' => $app->Application_id,
                    'program_chair_id' => $programChair->user->User_id,
                    'college_id' => $collegeId
                ]);
            } else {
                Log::warning('No Program Chairperson found for application', [
                    'application_id' => $app->Application_id,
                    'college_id' => $collegeId,
                    'student_id' => $studentId
                ]);
            }

            try {
                if (isset($data['context'])) {
                    $logDir  = storage_path('app/application_logs/'.$studentId);
                    File::ensureDirectoryExists($logDir);
                    $fname = 'app_'.$app->Application_id.'_'.now()->format('Ymd_His').'.json';
                    File::put($logDir.'/'.$fname, json_encode([
                        'Student_id'  => $studentId,
                        'Post_id'     => $postId,
                        'Application' => [
                            'Application_id' => $app->Application_id,
                            'Type'           => $type,
                            'File_name'      => $storeName,
                            'GWA'            => $gwaToStore,
                            'Rank'           => $rankToStore,
                            'Status'         => $app->Status,
                        ],
                        'context' => $data['context'],
                    ], JSON_PRETTY_PRINT));
                }
            } catch (\Throwable $logErr) {
                Log::warning('Application audit log failed', ['err' => $logErr->getMessage()]);
            }

            return response()->json([
                'ok'             => true,
                'application_id' => $app->Application_id,
                'post_id'        => $app->Post_id,
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

    public function regenerateDeanListForm()
    {
        abort(410, 'Legacy generator disabled. Use generateDeanListFormFromJson.');
    }

    /* =========================
     * PRIVATE HELPER METHODS
     * ========================= */
    private function extractTextFromPdfForCor(string $pdfAbs): string
    {
        if (!is_file($pdfAbs)) return '';

        // First try Smalot PDF Parser (pure PHP)
        try {
            if (class_exists(\Smalot\PdfParser\Parser::class)) {
                $parser = new \Smalot\PdfParser\Parser();
                $doc = $parser->parseFile($pdfAbs);
                $txt = (string) $doc->getText();
                return str_replace("\r\n", "\n", $txt);
            }
        } catch (\Throwable $e) {
            \Log::warning('COR Smalot fallback failed', ['err'=>$e->getMessage()]);
        }

        // Only try shell_exec if it's available
        if ($this->shellExecAvailable()) {
            $bins = [];
            $env1 = trim((string) env('PDFTOTEXT_PATH', ''));
            $env2 = trim((string) env('POPPLER_BIN', ''));
            $env3 = trim((string) env('POPPLER_BIN_DIR', ''));
            $env4 = trim((string) env('XPDF_BIN_DIR', ''));
            if ($env1 !== '') $bins[] = rtrim($env1, '/');
            if ($env2 !== '') $bins[] = rtrim($env2, '/');
            if ($env3 !== '') $bins[] = rtrim($env3, '/').'/pdftotext';
            if ($env4 !== '') $bins[] = rtrim($env4, '/').'/pdftotext';
            $bins[] = 'pdftotext';

            foreach ($bins as $bin) {
                try {
                    $ver = @shell_exec(escapeshellcmd($bin).' -v 2>&1');
                    if ($ver === null) continue;

                    $cmd = escapeshellcmd($bin).' -layout -eol unix '.escapeshellarg($pdfAbs).' -';
                    $out = @shell_exec($cmd.' 2>/dev/null');
                    if (is_string($out) && trim($out) !== '') {
                        return str_replace("\r\n", "\n", $out);
                    }
                } catch (\Throwable $e) { 
                    Log::warning('pdftotext command failed', ['bin' => $bin, 'error' => $e->getMessage()]);
                }
            }
        } else {
            Log::info('shell_exec not available, using PHP-based PDF parsing only');
        }

        return '';
    }

    private function shellExecAvailable(): bool
    {
        // Check if shell_exec function exists and is not disabled
        if (!function_exists('shell_exec')) {
            Log::warning('shell_exec function does not exist');
            return false;
        }
        
        $disabled = (string) ini_get('disable_functions');
        if ($disabled && stripos($disabled, 'shell_exec') !== false) {
            Log::warning('shell_exec is disabled in php.ini');
            return false;
        }
        
        return true;
    }

    private function tryAutoDecodeQrFromPdfOrPng(?string $pdfAbsOrNull): array
    {
        $dir = storage_path('app/cog');
        $this->ensureDir($dir);

        $pngAbs = $dir.'/cog_upload.png';
        if (is_file($pngAbs)) {
            $decoded = $this->decodeQrFromImage($pngAbs);
            if ($decoded !== '') {
                File::put($dir.'/_debug_qr_decoded_from_image.txt', $decoded."\n");
                return ['decoded'=>$decoded, 'source'=>'png', 'aux'=>$pngAbs];
            }
        }

        $pdfAbs = $pdfAbsOrNull && is_file($pdfAbsOrNull) ? $pdfAbsOrNull
                : (is_file($dir.'/cog_upload.pdf') ? $dir.'/cog_upload.pdf' : null);

        if ($pdfAbs) {
            $tmpPng = $this->pdfFirstPageToPng($pdfAbs, 220);
            if ($tmpPng && is_file($tmpPng)) {
                $decoded = $this->decodeQrFromImage($tmpPng);
                @unlink($tmpPng);
                if ($decoded !== '') {
                    File::put($dir.'/_debug_qr_decoded_from_image.txt', $decoded."\n");
                    return ['decoded'=>$decoded, 'source'=>'pdf', 'aux'=>$pdfAbs];
                }
            }
        }

        return ['decoded'=>'', 'source'=>'none', 'aux'=>''];
    }

    private function decodeQrFromImage(string $abs): string
    {
        try {
            if (!is_file($abs)) return '';
            $reader  = new \Zxing\QrReader($abs);
            $decoded = trim((string) $reader->text());
            return $decoded ?: '';
        } catch (\Throwable $e) {
            Log::warning('decodeQrFromImage failed', ['file'=>$abs, 'err'=>$e->getMessage()]);
            return '';
        }
    }

    private function pdfFirstPageToPng(string $pdfAbs, int $dpi = 220): ?string
    {
        if (!is_file($pdfAbs)) return null;

        try {
            if (class_exists(\Imagick::class)) {
                $tmpPng = storage_path('app/tmp/qr_render_'.uniqid().'.png');
                File::ensureDirectoryExists(dirname($tmpPng));
                $im = new \Imagick();
                $im->setResolution($dpi, $dpi);
                $im->readImage($pdfAbs.'[0]');
                $im->setImageFormat('png');
                $im->setImageBackgroundColor('white');
                $im = $im->flattenImages();
                $im->writeImage($tmpPng);
                $im->clear();
                $im->destroy();
                return is_file($tmpPng) ? $tmpPng : null;
            }
        } catch (\Throwable $e) {
            Log::warning('Imagick pdfFirstPageToPng failed', ['err'=>$e->getMessage()]);
        }

        // Only try shell_exec if available
        if ($this->shellExecAvailable()) {
            try {
                $cands = [];
                $ppmm  = trim((string) env('PDFTOPPM_PATH', ''));
                $popDir= trim((string) env('POPPLER_BIN_DIR', ''));
                $xpdf  = trim((string) env('XPDF_BIN_DIR', ''));

                if ($ppmm !== '')         $cands[] = rtrim($ppmm, '/');
                if ($popDir !== '')       $cands[] = rtrim($popDir, '/') . '/pdftoppm';
                if ($xpdf !== '')         $cands[] = rtrim($xpdf, '/') . '/pdftoppm';
                $cands[] = 'pdftoppm';

                foreach ($cands as $pdftoppm) {
                    $ver = @\shell_exec(\escapeshellcmd($pdftoppm).' -v 2>&1');
                    if ($ver === null) continue;

                    $tmpPrefix = storage_path('app/tmp/qrppm_'.uniqid());
                    File::ensureDirectoryExists(dirname($tmpPrefix));
                    $cmd = \escapeshellcmd($pdftoppm).' -r '.((int)$dpi).' -f 1 -l 1 -png '.\escapeshellarg($pdfAbs).' '.\escapeshellarg($tmpPrefix);
                    @\shell_exec($cmd.' 2>/dev/null');

                    $png = $tmpPrefix.'-1.png';
                    if (is_file($png)) return $png;
                }
            } catch (\Throwable $e) {
                Log::warning('pdftoppm path failed', ['err'=>$e->getMessage()]);
            }
        }

        Log::warning('pdfFirstPageToPng: no renderer available (Imagick/pdftoppm).');
        return null;
    }

    private function writeCogOcrFromPdfIfPossible(?string $pdfAbs): bool
    {
        $dir = storage_path('app/cog');
        $this->ensureDir($dir);
        $dst = $dir . '/cog_ocr_output.txt';

        // Piliin kung anong PDF ang gagamitin
        $pdf = $pdfAbs && is_file($pdfAbs)
            ? $pdfAbs
            : (is_file($dir.'/cog_upload.pdf') ? $dir.'/cog_upload.pdf' : null);

        if (!$pdf) {
            Log::warning('writeCogOcrFromPdfIfPossible: no PDF found.');
            return false;
        }

        // Kuhanin ang raw text mula sa PDF
        $raw = $this->extractTextFromPdfSmart($pdf);
        if ($raw === '') {
            Log::warning('writeCogOcrFromPdfIfPossible: extractors returned empty text.');
            return false;
        }

        $sha    = @hash_file('sha256', $pdf) ?: '';
        $header = "SOURCE: OCR_FROM_PDF sha256={$sha} path={$pdf}\n";

        // EXACT requirement mo: header + RAW TEXT LANG
        File::put($dst, $header.$raw);

        return true;
    }

    private function extractTextFromPdfSmart(string $pdfAbs): string
    {
        if (!is_file($pdfAbs)) return '';

        try {
            if (class_exists(\Smalot\PdfParser\Parser::class)) {
                $parser = new \Smalot\PdfParser\Parser();
                $doc = $parser->parseFile($pdfAbs);
                $txt = $doc->getText();
                $norm = $this->normalizePdfText((string)$txt);
                if ($norm !== '') return $norm;
            } else {
                Log::warning('Smalot\\PdfParser not installed.');
            }
        } catch (\Throwable $e) {
            Log::warning('Smalot parser failed', ['err' => $e->getMessage()]);
        }

        // Only try shell_exec if available
        if ($this->shellExecAvailable()) {
            $candidates = [];
            $bin       = trim((string) env('PDFTOTEXT_PATH', ''));
            $popplerDir= trim((string) env('POPPLER_BIN_DIR', ''));
            $popplerBin= trim((string) env('POPPLER_BIN', ''));
            $xpdfDir   = trim((string) env('XPDF_BIN_DIR', ''));

            if ($bin !== '')         $candidates[] = rtrim($bin, '/');
            if ($popplerBin !== '')  $candidates[] = rtrim($popplerBin, '/');
            if ($popplerDir !== '')  $candidates[] = rtrim($popplerDir, '/') . '/pdftotext';
            if ($xpdfDir !== '')     $candidates[] = rtrim($xpdfDir, '/') . '/pdftotext';
            $candidates[] = 'pdftotext';

            foreach ($candidates as $cand) {
                $txt = $this->runPdftotext($cand, $pdfAbs);
                if ($txt !== '') return $this->normalizePdfText($txt);
            }
        } else {
            Log::info('pdftotext skipped: shell_exec not available on host.');
        }

        return '';
    }

    private function runPdftotext(string $bin, string $pdfAbs): string
    {
        if (!$this->shellExecAvailable()) return '';

        try {
            $ver = @\shell_exec(\escapeshellcmd($bin).' -v 2>&1');
            if ($ver === null) return '';

            $cmd = \escapeshellcmd($bin).' -enc UTF-8 -layout '.\escapeshellarg($pdfAbs).' -';
            $out = @\shell_exec($cmd.' 2>/dev/null');
            if (is_string($out) && trim($out) !== '') return trim($out);

            $tmp = storage_path('app/tmp/'.Str::random(10).'.txt');
            File::ensureDirectoryExists(dirname($tmp));
            $cmd2 = \escapeshellcmd($bin).' -enc UTF-8 -layout '.\escapeshellarg($pdfAbs).' '.\escapeshellarg($tmp);
            @\shell_exec($cmd2.' 2>/dev/null');
            if (is_file($tmp)) {
                $txt = (string) File::get($tmp);
                @unlink($tmp);
                return trim($txt);
            }
        } catch (\Throwable $e) {
            Log::warning('pdftotext failed', ['bin'=>$bin,'err'=>$e->getMessage()]);
        }
        return '';
    }

    private function normalizePdfText(string $txt): string
    {
        if ($txt === '') return '';
        $txt = str_replace("\r\n", "\n", $txt);
        $txt = preg_replace("/[ \t]+\n/", "\n", $txt);
        $txt = preg_replace('/\x{00A0}+/u', ' ', $txt);
        return rtrim($txt)."\n";
    }

    private function formatOcrPretty(array $meta, array $rows): string
    {
        return $this->buildAsciiGradesTextExact('', $meta, $rows);
    }

    private function buildGradesOnlyTable(array $rows): string
    {
        $L = [];
        $L[] = '| # | Grade |';
        $L[] = '|---|-------|';
        foreach ($rows as $i => $r) {
            $g = (string) ($r['grade'] ?? '');
            $g = $this->normalizeGradeToken($g);
            $L[] = '| ' . ($i + 1) . ' | ' . ($g !== '' ? $g : '—') . ' |';
        }
        $L[] = '';
        return implode("\n", $L);
    }

    private function normalizeGradeToken(string $tok): string
    {
        $t = strtoupper(trim($tok));
        if ($t === 'INCOMPLETE') $t = 'INC';
        if ($t === 'DROP')       $t = 'DRP';
        if (in_array($t, ['INC','DRP','W'], true)) return $t;
        if ($t === '100') return '1.00';
        if ($t === '125') return '1.25';
        if ($t === '150') return '1.50';
        if ($t === '175') return '1.75';
        if ($t === '200') return '2.00';
        if (preg_match('/^\d(?:\.\d{1,4})?$/', $t)) return number_format((float)$t, 2);
        $just = preg_replace('/[^0-9.]/', '', $t);
        return $just !== '' ? number_format((float)$just, 2) : '';
    }

    private function writeGradesMismatchReport(array $rowsQR, array $rowsOCR): void
    {
        try {
            $dir = storage_path('app/cog');
            $this->ensureDir($dir);

            $N = max(count($rowsQR), count($rowsOCR));
            $L = [];
            $L[] = '| # | Code | QR | OCR |';
            $L[] = '|---|------|----|-----|';

            for ($i = 0; $i < $N; $i++) {
                $qr   = $rowsQR[$i]['grade']  ?? '';
                $ocr  = $rowsOCR[$i]['grade'] ?? '';
                $code = $rowsQR[$i]['code']   ?? ($rowsOCR[$i]['code'] ?? '');
                $qrN  = $this->normalizeGradeToken((string)$qr);
                $ocrN = $this->normalizeGradeToken((string)$ocr);
                if ($qrN !== $ocrN) {
                    $L[] = sprintf('| %d | %s | %s | %s |', $i+1, $code ?: '—', $qrN ?: '—', $ocrN ?: '—');
                }
            }

            File::put($dir.'/grades_mismatches.txt', implode("\n", $L)."\n");
        } catch (\Throwable $e) {
            Log::debug('writeGradesMismatchReport failed', ['err'=>$e->getMessage()]);
        }
    }

    private function readGradesOnly(string $abs): array
    {
        if (!is_file($abs)) return [];
        $out = [];
        foreach (explode("\n", (string) File::get($abs)) as $line) {
            if (preg_match('/^\s*\|\s*\d+\s*\|\s*([A-Z0-9.]+|—)\s*\|\s*$/', trim($line), $m)) {
                $out[] = $m[1] === '—' ? '' : $m[1];
            }
        }
        return $out;
    }

    private function parseQrPayload(string $p): ?array
    {
        if (preg_match('#^https?://#i', $p)) {
            $url = parse_url($p);
            if (!empty($url['query'])) {
                parse_str($url['query'], $qs);
                $raw = $qs['d'] ?? ($qs['data'] ?? '');
                if ($raw) return $this->parseQrPayload($raw);
            }
            return null;
        }

        $decoded = base64_decode($p, true);
        if ($decoded !== false) {
            $j = json_decode($decoded, true);
            if (is_array($j)) return $this->mapQrJsonToParsed($j);
        }

        $j2 = json_decode($p, true);
        if (is_array($j2)) return $this->mapQrJsonToParsed($j2);

        return null;
    }

    private function extractQrUrlFromPayload(string $p): ?string
    {
        if (preg_match('#^https?://#i', $p)) return trim($p);

        $decoded = base64_decode($p, true);
        if ($decoded !== false && $decoded !== '') {
            $j = json_decode($decoded, true);
            if (is_array($j)) {
                foreach (['url','qr_url','link','href'] as $k) {
                    if (!empty($j[$k]) && preg_match('#^https?://#i', (string)$j[$k])) return (string)$j[$k];
                }
                $it = new \RecursiveIteratorIterator(new \RecursiveArrayIterator($j));
                foreach ($it as $val) {
                    if (is_string($val) && preg_match('#https?://[^\s"\'<>]+#i', $val, $m)) return $m[0];
                }
            }
            if (preg_match('#https?://[^\s"\'<>]+#i', $decoded, $m)) return $m[0];
        }

        $j2 = json_decode($p, true);
        if (is_array($j2)) {
            foreach (['url','qr_url','link','href'] as $k) {
                if (!empty($j2[$k]) && preg_match('#^https?://#i', (string)$j2[$k])) return (string)$j2[$k];
            }
            $it = new \RecursiveIteratorIterator(new \RecursiveArrayIterator($j2));
            foreach ($it as $val) {
                if (is_string($val) && preg_match('#https?://[^\s"\'<>]+#i', $val, $m)) return $m[0];
            }
        }

        if (preg_match('#https?://[^\s"\'<>]+#i', $p, $m)) return $m[0];
        return null;
    }

    private function mapQrJsonToParsed(array $j): array
    {
        $header = (array) ($j['header'] ?? []);
        $grades = (array) ($j['grades'] ?? []);

        $meta = [
            'fullname'      => (string) ($header['Fullname'] ?? ''),
            'srcode'        => (string) ($header['SRCODE'] ?? ''),
            'college'       => (string) ($header['College'] ?? ''),
            'academic_year' => (string) ($header['Academic Year'] ?? ''),
            'program'       => (string) ($header['Program'] ?? ''),
            'semester'      => (string) ($header['Semester'] ?? ''),
            'year_level'    => (string) ($header['Year Level'] ?? ''),
            'total_units'   => isset($j['total_units']) ? (string) $j['total_units'] : '',
            'total_courses' => isset($j['total_courses']) ? (string) $j['total_courses'] : '',
            'gwa'           => isset($j['gwa']) ? (string) $j['gwa'] : '',
        ];

        $rows = [];
        foreach ($grades as $i => $g) {
            $name = trim((string) ($g['name'] ?? ''));
            [$code, $title] = $this->splitCodeTitle($name);
            $rows[] = [
                'idx'        => $i + 1,
                'code'       => $code,
                'title'      => $title,
                'units'      => isset($g['units']) ? (string) $g['units'] : '',
                'grade'      => isset($g['grade']) ? (string) $g['grade'] : '',
                'section'    => (string) ($g['section'] ?? ''),
                'instructor' => (string) ($g['instructor'] ?? ''),
            ];
        }

        return ['header' => $header, 'meta' => $meta, 'grades' => $rows];
    }

    private function buildAsciiGradesTextExact(string $qrUrl, array $meta, array $rows): string
    {
        // === Compute summary values (Courses, Units, GWA) ===
        $courseCount = count($rows);
        $totalUnits  = 0.0;
        $weighted    = 0.0;

        foreach ($rows as $r) {
            $u     = (float)($r['units'] ?? 0);
            $gRaw  = (string)($r['grade'] ?? '');
            $gNum  = null;

            // Support formats like "INC/2.00" -> use 2.00 as numeric value
            if (preg_match('/(\d+(?:\.\d+)?)(?!.*\d)/', $gRaw, $m)) {
                $gNum = (float)$m[1];
            } elseif (is_numeric($gRaw)) {
                $gNum = (float)$gRaw;
            }

            $totalUnits += $u;
            if ($u > 0 && $gNum !== null) {
                $weighted += $u * $gNum;
            }
        }

        $gwa = ($totalUnits > 0 && $weighted > 0)
            ? round($weighted / $totalUnits, 4)
            : '';

        $shortNow = now()->format('n/j/y, g:i A');

        $fullname      = (string)($meta['fullname']      ?? '');
        $srcode        = (string)($meta['srcode']        ?? '');
        $college       = (string)($meta['college']       ?? '');
        $ay            = (string)($meta['academic_year'] ?? '');
        $program       = (string)($meta['program']       ?? '');
        $semester      = (string)($meta['semester']      ?? '');
        $yearLevel     = (string)($meta['year_level']    ?? '');

        $L = [];

        // === First line (date + title) ===
        $L[] = $shortNow.'                         Batangas State University - Student Copy of Grades';
        $L[] = '';

        // === PARSED DATA block ===
        $L[] = 'PARSED DATA:';
        $L[] = 'Fullname: '.$fullname;
        $L[] = 'SRCODE: '.$srcode;
        $L[] = 'College: '.$college;
        $L[] = 'Academic Year: '.$ay;
        $L[] = 'Program: '.$program;
        $L[] = 'Semester: '.$semester;
        $L[] = 'Year Level: '.$yearLevel;
        $L[] = '';

        // === COURSES block ===
        $L[] = 'COURSES:';
        foreach ($rows as $r) {
            $code       = (string)($r['code']       ?? '');
            $title      = (string)($r['title']      ?? '');
            $units      = (string)($r['units']      ?? '');
            $grade      = (string)($r['grade']      ?? '');
            $section    = (string)($r['section']    ?? '');
            $instructor = (string)($r['instructor'] ?? '');

            // e.g.:
            // IT 331 | Application Development and Emerging Technologies | 3 | INC/2.00 | IT-BA-3301 | HERNANDEZ, OLIVER M.
            $L[] = sprintf(
                '%s | %s | %s | %s | %s | %s',
                $code,
                $title,
                $units,
                $grade,
                $section,
                $instructor
            );
        }
        $L[] = '';

        // === SUMMARY block ===
        $L[] = 'SUMMARY:';
        $L[] = 'Total Courses: '.$courseCount;
        $L[] = 'Total Units: '.(int)$totalUnits;
        $L[] = 'GWA: '.($gwa !== '' ? number_format($gwa, 4) : '');
        $L[] = '';

        // === RAW EXTRACTED TEXT block (reconstructed version from parsed data) ===
        $L[] = 'RAW EXTRACTED TEXT:';
        $L[] = 'BATANGAS STATE UNIVERSITY';
        $L[] = 'ARASOF-Nasugbu Campus';
        $L[] = "Student's Copy of Grades";
        $L[] = 'Fullname: '.$fullname."\t".'SRCODE: '.$srcode;
        $L[] = 'College: '.$college."\t".'Academic Year: '.$ay;
        $L[] = 'Program: '.$program."\t".'Semester: '.$semester;
        $L[] = 'Year Level: '.$yearLevel;
        $L[] = '#Course Code'."\t".'Course Title'."\t".'Units'.'Grade '.'Section'."\t".'Instructor';

        foreach ($rows as $i => $r) {
            $code       = (string)($r['code']       ?? '');
            $title      = (string)($r['title']      ?? '');
            $units      = (string)($r['units']      ?? '');
            $grade      = (string)($r['grade']      ?? '');
            $section    = (string)($r['section']    ?? '');
            $instructor = (string)($r['instructor'] ?? '');

            // Ginaya yung style ng sample mo, naka-tab at may index
            $L[] = sprintf(
                '%d %s %s%s%s %s %s',
                $i + 1,
                $code,
                $title."\t",
                $units.' ',
                $grade,
                $section,
                $instructor
            );
        }

        $L[] = '** NOTHING FOLLOWS **';
        $L[] = 'Total no of Course '.$courseCount;
        $L[] = 'Total no of Units '.(int)$totalUnits;
        $L[] = 'General Weighted Average (GWA) '.($gwa !== '' ? rtrim(rtrim(number_format($gwa, 4), '0'), '.') : '');

        // Last lines gaya ng original (datetime + SRCODE + URL page)
        $now2 = now()->format('Y-m-d h:i:s A');
        $src  = $srcode;
        $L[]  = $now2;
        if ($src) {
            $L[] = $src;
        }
        $L[] = $shortNow."\tBatangas State University - Student Copy of Grades";
        if ($qrUrl) {
            $L[] = $qrUrl.' 1/1';
        }

        return implode("\n", $L)."\n";
    }

    private function splitCodeTitle(string $name): array
    {
        if (preg_match('/^([A-Za-z]{2,}(?:\s*[-\/]\s*[A-Za-z]{1,3})?\s*\d{2,4}[A-Za-z]?)\s+(.+)$/', $name, $m)) {
            return [$m[1], $m[2]];
        }
        return ['', $name];
    }

    private function normalizeQrUrl(?string $u): string
    {
        if (!$u) return '';
        $u = trim($u);

        $hash = strpos($u, '#');
        if ($hash !== false) $u = substr($u, 0, $hash);

        $p = @parse_url($u);
        if (!$p || empty($p['scheme']) || empty($p['host'])) {
            return $u;
        }

        $path = (string) ($p['path'] ?? '');
        $path = preg_replace('#/\d+/?$#', '', $path);

        $query = isset($p['query']) ? preg_replace('/\s+/', '', $p['query']) : '';

        return $p['scheme'].'://'
                .$p['host']
                .(isset($p['port']) ? (':'.$p['port']) : '')
                .$path
                .($query !== '' ? '?'.$query : '');
    }

    private function fetchQrText(string $url): string
    {
        $orig = $url;
        $url  = $this->normalizeQrUrl($url) ?: $orig;

        try {
            $res = Http::retry(2, 300)
                ->timeout(20)
                ->withoutVerifying()
                ->withOptions(['allow_redirects' => true])
                ->withHeaders([
                    'User-Agent'      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120 Safari/537.36',
                    'Accept'          => 'text/html,application/xhtml+xml,application/xml;q=0.9,application/pdf;q=0.9,*/*;q=0.8',
                    'Accept-Language' => 'en-US,en;q=0.9',
                    'Referer'         => 'https://dione.batstate-u.edu.ph/student/',
                    'Connection'      => 'keep-alive',
                ])->get($url);
        } catch (\Throwable $e) {
            Log::warning('fetchQrText transport error', ['url'=>$url,'err'=>$e->getMessage()]);
            return '';
        }

        if (!$res->ok()) {
            Log::warning('fetchQrText non-200', ['url'=>$url,'code'=>$res->status()]);
            return '';
        }

        $ctype = strtolower($res->header('content-type', ''));
        $body  = (string) $res->body();

        if (str_contains($ctype, 'application/pdf') || preg_match('/^\s*%PDF-/', $body)) {
            $tmpPdf = storage_path('app/tmp_qr_'.uniqid().'.pdf');
            File::ensureDirectoryExists(dirname($tmpPdf));
            file_put_contents($tmpPdf, $body);
            $txt = $this->extractTextFromPdfSmart($tmpPdf);
            @unlink($tmpPdf);
            return $txt;
        }

        $jsonTxt = $this->extractGradesJsonFromHtml($body);
        if ($jsonTxt !== '') return $jsonTxt;

        $pdfUrl = $this->extractPdfUrlFromHtml($body, $url);
        if ($pdfUrl) {
            try {
                $pdfRes = Http::retry(2, 300)->timeout(20)->withoutVerifying()->get($pdfUrl);
                if ($pdfRes->ok()) {
                    $b = (string) $pdfRes->body();
                    if (str_contains(strtolower($pdfRes->header('content-type','')), 'application/pdf') || preg_match('/^\s*%PDF-/', $b)) {
                        $tmpPdf = storage_path('app/tmp_qr_'.uniqid().'.pdf');
                        File::ensureDirectoryExists(dirname($tmpPdf));
                        file_put_contents($tmpPdf, $b);
                        $txt = $this->extractTextFromPdfSmart($tmpPdf);
                        @unlink($tmpPdf);
                        if ($txt !== '') return $txt;
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('fetchQrText: PDF follow failed', ['url'=>$pdfUrl,'err'=>$e->getMessage()]);
            }
        }

        $html = $body;
        $html = preg_replace('~<br\s*/?>~i', "\n", $html);
        $html = preg_replace('~</(p|div|li|tr)>~i', "\n", $html);
        $html = preg_replace('~</t[dh]>~i', "\t", $html);
        $text = strip_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace("/[ \t]+\t/", "\t", $text);
        $text = preg_replace("/\t{2,}/", "\t", $text);
        $text = preg_replace("/[ \t]+/", " ", $text);
        $text = preg_replace("/\n{2,}/", "\n", $text);
        return trim($text);
    }

    private function extractGradesJsonFromHtml(string $html): string
    {
        if (preg_match('~window\.(?:__GRADES__|GRADES|__DATA__)\s*=\s*({.*?});~s', $html, $m)) {
            $j = json_decode($m[1], true);
            if (is_array($j)) {
                $parsed = $this->mapQrJsonToParsed($j);
                if (!empty($parsed['grades'])) return $this->plainTextFromParsed($parsed);
            }
        }
        if (preg_match('~<script[^>]*type=["\']application/json["\'][^>]*>(.*?)</script>~si', $html, $m2)) {
            $j = json_decode(html_entity_decode($m2[1], ENT_QUOTES|ENT_HTML5, 'UTF-8'), true);
            if (is_array($j)) {
                $parsed = $this->mapQrJsonToParsed($j);
                if (!empty($parsed['grades'])) return $this->plainTextFromParsed($parsed);
            }
        }
        return '';
    }

    private function extractPdfUrlFromHtml(string $html, string $baseUrl): ?string
    {
        if (preg_match('~(?:href|src)\s*=\s*["\']([^"\']+\.pdf(?:\?[^"\']*)?)["\']~i', $html, $m)) {
            return $this->absolutizeUrl($m[1], $baseUrl);
        }
        if (preg_match('~(?:href|src)\s*=\s*["\']([^"\']*(?:print|download)[^"\']*)["\']~i', $html, $m2)) {
            return $this->absolutizeUrl($m2[1], $baseUrl);
        }
        return null;
    }

    private function absolutizeUrl(string $maybeRel, string $base): string
    {
        if (preg_match('#^https?://#i', $maybeRel)) return $maybeRel;
        $p = parse_url($base);
        if (!$p || empty($p['scheme']) || empty($p['host'])) return $maybeRel;
        $root = $p['scheme'].'://'.$p['host'].(isset($p['port'])?':'.$p['port']:'');
        if (str_starts_with($maybeRel, '/')) return $root.$maybeRel;
        $dir = isset($p['path']) ? rtrim(dirname($p['path']), '/') : '';
        return $root.$dir.'/'.$maybeRel;
    }

    /**
     * Convert 16-bit PNG to 8-bit PNG for FPDF compatibility
     */
    private function convertPngTo8Bit(string $sourcePath, string $destPath): bool
    {
        try {
            if (!file_exists($sourcePath)) {
                Log::warning('Source PNG not found for conversion', ['path' => $sourcePath]);
                return false;
            }

            $imageInfo = getimagesize($sourcePath);
            if (!$imageInfo) {
                Log::warning('Invalid PNG file', ['path' => $sourcePath]);
                return false;
            }

            // Check if it's already 8-bit
            $image = imagecreatefrompng($sourcePath);
            if (!$image) {
                Log::warning('Failed to create image from PNG', ['path' => $sourcePath]);
                return false;
            }

            // Get image dimensions
            $width = imagesx($image);
            $height = imagesy($image);

            // Create new 8-bit image
            $newImage = imagecreatetruecolor($width, $height);
            
            // Preserve transparency
            imagealphablending($newImage, false);
            imagesavealpha($newImage, true);
            $transparent = imagecolorallocatealpha($newImage, 0, 0, 0, 127);
            imagefill($newImage, 0, 0, $transparent);

            // Copy pixels from 16-bit to 8-bit
            for ($x = 0; $x < $width; $x++) {
                for ($y = 0; $y < $height; $y++) {
                    $color = imagecolorat($image, $x, $y);
                    $rgba = imagecolorsforindex($image, $color);
                    $newColor = imagecolorallocatealpha($newImage, $rgba['red'], $rgba['green'], $rgba['blue'], $rgba['alpha']);
                    imagesetpixel($newImage, $x, $y, $newColor);
                }
            }

            // Save as 8-bit PNG
            $success = imagepng($newImage, $destPath, 9); // Maximum compression
            
            // Clean up
            imagedestroy($image);
            imagedestroy($newImage);

            Log::info('PNG conversion result', [
                'source' => $sourcePath,
                'dest' => $destPath,
                'success' => $success,
                'dest_exists' => file_exists($destPath),
                'dest_size' => file_exists($destPath) ? filesize($destPath) : 0
            ]);

            return $success && file_exists($destPath);

        } catch (\Exception $e) {
            Log::error('PNG conversion failed', [
                'source' => $sourcePath,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    private function plainTextFromParsed(array $parsed): string
    {
        $meta = (array)($parsed['meta'] ?? []);
        $rows = (array)($parsed['grades'] ?? []);

        $L = [];
        $L[] = "Fullname : ".($meta['fullname'] ?? '');
        $L[] = "SRCODE : ".($meta['srcode'] ?? '');
        $L[] = "College : ".($meta['college'] ?? '');
        $L[] = "Academic Year : ".($meta['academic_year'] ?? '');
        $L[] = "Program : ".($meta['program'] ?? '');
        $L[] = "Semester : ".($meta['semester'] ?? '');
        $L[] = "Year Level : ".($meta['year_level'] ?? '');
        $L[] = "# Course Code\tCourse Title\tUnits\tGrade\tSection\tInstructor";
        foreach ($rows as $i => $g) {
            $L[] = ($i+1)."\t".($g['code']??'')."\t".($g['title']??'')."\t".($g['units']??'')."\t".($g['grade']??'')."\t".($g['section']??'')."\t".($g['instructor']??'');
        }
        return implode("\n", $L);
    }

    private function sanitizeMeta(array $meta): array
    {
        foreach ($meta as $k => $v) {
            if (!is_string($v)) continue;
            $v = preg_replace('/^\s*[:\-–—]+\s*/u', '', $v);
            $v = preg_replace('/\s+/u', ' ', $v);
            $meta[$k] = trim($v);
        }
        return $meta;
    }

    private function parseQrGradesFromText(string $txt): array
    {
        $meta = [
            'fullname'      => '',
            'srcode'        => '',
            'college'       => '',
            'academic_year' => '',
            'program'       => '',
            'semester'      => '',
            'year_level'    => '',
        ];
        $rows = [];

        $txt   = str_replace("\xC2\xA0", ' ', $txt);
        $lines = array_values(array_filter(array_map(static fn($s)=>rtrim($s, "\r"), explode("\n", $txt)), static fn($s)=>$s!==null));

        $metaStack = $this->parseStackedHeader($lines);
        foreach ($lines as $ln) {
            if (!$meta['fullname']      && preg_match('/Fullname\s*:\s*(.+?)(?:\s+SRCODE|$)/i', $ln, $m))       $meta['fullname'] = trim($m[1]);
            if (!$meta['srcode']        && preg_match('/SRCODE\s*:\s*([0-9\-]+)/i', $ln, $m))                   $meta['srcode'] = trim($m[1]);
            if (!$meta['college']       && preg_match('/College\s*:\s*(.+?)(?:\s+Academic Year|$)/i', $ln, $m)) $meta['college'] = trim($m[1]);
            if (!$meta['academic_year'] && preg_match('/Academic Year\s*:\s*([0-9\-]+)/i', $ln, $m))            $meta['academic_year'] = trim($m[1]);
            if (!$meta['program']       && preg_match('/Program\s*:\s*(.+?)(?:\s+Semester|$)/i', $ln, $m))      $meta['program'] = trim($m[1]);
            if (!$meta['semester']      && preg_match('/Semester\s*:\s*([A-Z]+)/i', $ln, $m))                   $meta['semester'] = trim($m[1]);
            if (!$meta['year_level']    && preg_match('/Year Level\s*:\s*([A-Z]+)/i', $ln, $m))                 $meta['year_level'] = trim($m[1]);
        }
        $meta = array_filter($metaStack) + $meta;
        $meta = $this->sanitizeMeta($meta);

        $start = 0;
        foreach ($lines as $i => $ln) {
            if (stripos($ln, 'Course Code') !== false && stripos($ln, 'Course Title') !== false) { $start = $i + 1; break; }
        }

        $cleanUnits = static function($s){ $s = trim($s); return $s !== '' ? preg_replace('/[^0-9.]/', '', $s) : ''; };
        $cleanGrade = function($s){
            $t = strtoupper(trim($s));
            if (in_array($t, ['INC','DRP','W'], true)) return $t;
            if (preg_match('/^\d+(?:\.\d+)?$/', $t))    return number_format((float)$t, 2);
            $just = preg_replace('/[^0-9.]/', '', $t);
            return $just !== '' ? number_format((float)$just, 2) : '';
        };

        $patTab   = '/^\s*(\d+)\s*\t\s*([A-Za-z]{2,}(?:\s*[-\/]\s*[A-Za-z]{1,3})?\s*\d{2,4}[A-Za-z]?)\s*\t\s*(.+?)\s*\t\s*([0-9.]+)\s*\t\s*([0-9.]+|INC|DRP|W)\s*\t\s*([A-Z0-9\-]+)?\s*\t?(.*)$/u';
        $patLine  = '/^\s*\d+\s+([A-Za-z]{2,}(?:\s*[-\/]\s*[A-Za-z]{1,3})?\s*\d{2,4}[A-Za-z]?)\s+(.+?)\s+([0-9]+(?:\.[0-9]+)?)\s+([0-9.]{1,5}|INC|DRP|W)\s+([A-Z0-9\-]+)\s+(.*)$/u';
        $patLoose = '/^\s*\d+\s+([A-Za-z]{2,}(?:\s*[-\/]\s*[A-Za-z]{1,3})?\s*\d{2,4}[A-Za-z]?)\s+(.+?)\s+([0-9]+(?:\.[0-9]+)?)\s+([0-9.]{1,5}|INC|DRP|W)\b(.*)$/u';
        $patNoGrade='/^\s*\d+\s+([A-Za-z]{2,}(?:\s*[-\/]\s*[A-Za-z]{1,3})?\s*\d{2,4}[A-Za-z]?)\s+(.+?)\s+([0-9]+(?:\.[0-9]+)?)\s+(.*)$/u';
        $patSoloGrade= '/^\s*(\d(?:\.\d{1,4})?)\s*$/';

        $lastNeedsGrade = null;
        $rows = [];

        for ($i = $start; $i < count($lines); $i++) {
            $line = trim($lines[$i]);

            if (stripos($line, 'Total no of Course') !== false ||
                stripos($line, 'Total no of Units')  !== false ||
                stripos($line, 'General Weighted Average') !== false ||
                stripos($line, 'NOTHING FOLLOWS') !== false) {
                continue;
            }

            if ($lastNeedsGrade !== null && preg_match($patSoloGrade, $line, $gm)) {
                $rows[$lastNeedsGrade]['grade'] = $cleanGrade($gm[1]);
                $lastNeedsGrade = null;
                continue;
            }

            if (strpos($line, "\t") !== false && preg_match($patTab, $line, $m)) {
                $rows[] = [
                    'idx'        => (int)$m[1],
                    'code'       => trim($m[2]),
                    'title'      => trim($m[3]),
                    'units'      => $cleanUnits($m[4]),
                    'grade'      => $cleanGrade($m[5]),
                    'section'    => trim((string)($m[6] ?? '')),
                    'instructor' => trim((string)($m[7] ?? '')),
                ];
                $lastNeedsGrade = null;
                continue;
            }

            $c = preg_replace('/\s{2,}/', ' ', $line);

            if (preg_match($patLine, $c, $m)) {
                $rows[] = [
                    'idx'        => count($rows) + 1,
                    'code'       => trim($m[1]),
                    'title'      => trim($m[2]),
                    'units'      => $cleanUnits($m[3]),
                    'grade'      => $cleanGrade($m[4]),
                    'section'    => trim($m[5]),
                    'instructor' => trim($m[6]),
                ];
                $lastNeedsGrade = null;
                continue;
            }

            if (preg_match($patLoose, $c, $m)) {
                $tail = trim((string)($m[5] ?? ''));
                $section = '';
                $instr   = '';
                if (preg_match('/^([A-Z0-9\-]{3,})\s+(.*)$/u', $tail, $mm)) { $section = $mm[1]; $instr = $mm[2]; }
                else $instr = $tail;

                $rows[] = [
                    'idx'        => count($rows) + 1,
                    'code'       => trim($m[1]),
                    'title'      => trim($m[2]),
                    'units'      => $cleanUnits($m[3]),
                    'grade'      => $cleanGrade($m[4]),
                    'section'    => $section,
                    'instructor' => $instr,
                ];
                $lastNeedsGrade = null;
                continue;
            }

            if (preg_match($patNoGrade, $c, $m)) {
                $tail = trim((string)($m[4] ?? ''));
                $section = '';
                $instr   = '';
                if (preg_match('/^([A-Z0-9\-]{3,})\s+(.*)$/u', $tail, $mm)) { $section = $mm[1]; $instr = $mm[2]; }
                else $instr = $tail;

                $rows[] = [
                    'idx'        => count($rows) + 1,
                    'code'       => trim($m[1]),
                    'title'      => trim($m[2]),
                    'units'      => $cleanUnits($m[3]),
                    'grade'      => '',
                    'section'    => $section,
                    'instructor' => $instr,
                ];
                $lastNeedsGrade = count($rows) - 1;
                continue;
            }
        }

        if (empty($rows)) {
            $rows = $this->parseStackedHtmlGrades($lines);
        }

        return ['meta'=>$meta, 'rows'=>$rows];
    }

    private function parseStackedHeader(array $lines): array
    {
        $map = [
            'FULLNAME'       => 'fullname',
            'SRCODE'         => 'srcode',
            'COLLEGE'        => 'college',
            'ACADEMIC YEAR'  => 'academic_year',
            'PROGRAM'        => 'program',
            'SEMESTER'       => 'semester',
            'YEAR LEVEL'     => 'year_level',
        ];
        $out = [];
        $N = count($lines);
        $t = static fn($s) => (string) preg_replace('/\s+/u', ' ', trim((string)$s));

        for ($i = 0; $i < $N - 1; $i++) {
            $label = strtoupper(trim($lines[$i]));
            if (!isset($map[$label])) continue;

            $j = $i + 1;
            while ($j < $N && trim($lines[$j]) === '') $j++;
            if ($j < $N && trim($lines[$j]) === ':') {
                $j++;
                while ($j < $N && trim($lines[$j]) === '') $j++;
            }
            if ($j < $N) {
                $val = $t($lines[$j]);
                $val = preg_replace('/^\s*[:\-–—]+\s*/u', '', $val);
                $out[$map[$label]] = $val;
                $i = $j;
            }
        }
        return $out;
    }

    private function parseStackedHtmlGrades(array $lines): array
    {
        $rows = [];
        $N = count($lines);
        $i = 0;

        $isIdx   = static fn($s) => preg_match('/^\d+$/', trim($s));
        $isCode  = static fn($s) => preg_match('/^[A-Za-z]{2,}(?:\s*[-\/]\s*[A-Za-z]{1,3})?\s*\d{2,4}[A-Za-z]?$/u', trim($s));
        $isUnits = static fn($s) => preg_match('/^\d+(?:\.\d+)?$/', trim($s));
        $isGrade = static fn($s) => preg_match('/^(?:\d+(?:\.\d+)?|INC|DRP|W)$/i', trim($s));
        $nonEmpty = static fn($s) => trim($s) !== '';

        while ($i < $N) {
            if (!$isIdx($lines[$i] ?? '')) { $i++; continue; }

            $idx = (int) trim($lines[$i]); $i++;

            while ($i < $N && trim($lines[$i]) === '') $i++;
            if ($i >= $N || !$isCode($lines[$i])) { continue; }
            $code = trim($lines[$i]); $i++;

            while ($i < $N && trim($lines[$i]) === '') $i++;

            $titleParts = [];
            while ($i < $N && !$isUnits($lines[$i])) {
                if ($nonEmpty($lines[$i])) $titleParts[] = trim($lines[$i]);
                $i++;
            }
            $title = trim(implode(' ', $titleParts));

            if ($i >= $N || !$isUnits($lines[$i])) { continue; }
            $units = trim($lines[$i]); $i++;

            while ($i < $N && trim($lines[$i]) === '') $i++;
            if ($i >= $N || !$isGrade($lines[$i])) { continue; }
            $grade = strtoupper(trim($lines[$i])); $i++;

            while ($i < $N && trim($lines[$i]) === '') $i++;

            $section = '';
            if ($i < $N && preg_match('/^[A-Z0-9\-]{2,}$/', trim($lines[$i]))) {
                $section = trim($lines[$i]); $i++;
            }

            while ($i < $N && trim($lines[$i]) === '') $i++;
            $instructor = '';
            if ($i < $N && $nonEmpty($lines[$i])) {
                $instructor = trim($lines[$i]);
                $i++;
            }

            $rows[] = [
                'idx'        => $idx,
                'code'       => $code,
                'title'      => $title,
                'units'      => $units,
                'grade'      => $grade,
                'section'    => $section,
                'instructor' => $instructor,
            ];
        }

        return $rows;
    }

    /**
     * Canonicalize course code (upper + single space)
     */
    private function canonicalCourseCode(string $code): string
    {
        $code = strtoupper(trim($code));
        // collapse multiple spaces
        $code = preg_replace('/\s+/', ' ', $code);
        return $code;
    }

    /**
     * Parse course codes from COR output text (cor_output.txt)
     */
    private function parseCorCourseCodes(string $corText): array
    {
        $lines     = preg_split('/\R/', $corText);
        $codes     = [];
        $inCourses = false;

        foreach ($lines as $rawLine) {
            $line = trim($rawLine);
            if ($line === '') {
                continue;
            }

            // Start reading when we hit the COR header for courses
            if (stripos($line, 'COURSE CODE') === 0) {
                $inCourses = true;
                continue;
            }

            if ($inCourses) {
                // Stop when we reach Scholarship/Assessment/footer
                if (
                    stripos($line, 'Scholarship/s:') === 0 ||
                    stripos($line, 'ASSESSMENT') === 0 ||
                    stripos($line, 'Approved by:') === 0
                ) {
                    break;
                }

                // Typical line examples:
                // ES 101Environmental Sciences   3 (IT-2203)
                // GEd 101Understanding the Self  3 (IT-2203)
                // IT 221Information Management   3 (IT-2203)
                if (preg_match('/^([A-Za-z]{2,}\s*\d{3})/u', $line, $matches)) {
                    $code = $this->canonicalCourseCode($matches[1]);
                    $codes[$code] = true; // use assoc to dedupe
                }
            }
        }

        return array_keys($codes);
    }

    /**
     * Parse course codes from COG output text (cog_output.txt)
     */
    private function parseCogCourseCodes(string $cogText): array
    {
        $lines = preg_split('/\R/', $cogText);
        $codes = [];

        // 1) From COURSES: table near the top
        $inCoursesSection = false;

        foreach ($lines as $rawLine) {
            $line = trim($rawLine);
            if ($line === '') {
                continue;
            }

            if (stripos($line, 'COURSES:') === 0) {
                $inCoursesSection = true;
                continue;
            }

            if ($inCoursesSection) {
                // End of COURSES block
                if (
                    stripos($line, 'SUMMARY:') === 0 ||
                    stripos($line, 'RAW EXTRACTED TEXT:') === 0
                ) {
                    $inCoursesSection = false;
                    continue;
                }

                // Example:
                // ES 101 | Environmental Sciences | 3 | 1.50 | IT-2203 | MERCADO, ALBERT S.
                if (strpos($line, '|') !== false) {
                    $parts = array_map('trim', explode('|', $line));
                    if (!empty($parts[0]) && preg_match('/^[A-Za-z]{2,}\s*\d{3}$/u', $parts[0])) {
                        $code = $this->canonicalCourseCode($parts[0]);
                        $codes[$code] = true;
                    }
                }
            }
        }

        // 2) Fallback: from RAW EXTRACTED TEXT block
        foreach ($lines as $rawLine) {
            $line = trim($rawLine);
            // Example:
            // 1 ES 101 Environmental Sciences  3 1.50 IT-2203 MERCADO, ALBERT S.
            if (preg_match('/^\d+\s+([A-Za-z]{2,}\s*\d{3})\b/u', $line, $m)) {
                $code = $this->canonicalCourseCode($m[1]);
                $codes[$code] = true;
            }
        }

        return array_keys($codes);
    }

    /**
     * Compare COR vs COG course codes.
     * Returns array with match flag, and codes only-in-COR / only-in-COG.
     */
    private function compareCorCogCourseCodes(): array
    {
        $corPath = storage_path('app/cor/cor_output.txt');
        $cogPath = storage_path('app/cog/cog_output.txt');

        if (!is_file($corPath) || !is_file($cogPath)) {
            return [
                'ready'       => false,
                'match'       => false,
                'only_in_cor' => [],
                'only_in_cog' => [],
                'reason'      => 'COR/COG output file missing',
            ];
        }

        $corText = (string) file_get_contents($corPath);
        $cogText = (string) file_get_contents($cogPath);

        $corCodes = $this->parseCorCourseCodes($corText);
        $cogCodes = $this->parseCogCourseCodes($cogText);

        // canonical arrays
        sort($corCodes);
        sort($cogCodes);

        $onlyInCor = array_values(array_diff($corCodes, $cogCodes));
        $onlyInCog = array_values(array_diff($cogCodes, $corCodes));

        $match = empty($onlyInCor) && empty($onlyInCog);

        return [
            'ready'       => true,
            'match'       => $match,
            'cor_codes'   => $corCodes,
            'cog_codes'   => $cogCodes,
            'only_in_cor' => $onlyInCor,
            'only_in_cog' => $onlyInCog,
        ];
    }

    /**
     * Check if courses in COR and COG match (by course code only).
     * To be called via AJAX from the front-end.
     */
    public function checkCorCogCodes()
    {
        $result = $this->compareCorCogCourseCodes();

        if (!$result['ready']) {
            return response()->json([
                'ok'      => false,
                'match'   => false,
                'message' => 'COR/COG output not ready yet.',
            ], 200);
        }

        if ($result['match']) {
            return response()->json([
                'ok'      => true,
                'match'   => true,
                'message' => 'Courses in COR and COG match.',
                'cor'     => $result['cor_codes'],
                'cog'     => $result['cog_codes'],
            ], 200);
        }

        // May hindi nag-match
        return response()->json([
            'ok'          => true,
            'match'       => false,
            'message'     => 'The Course in your COR and COG do not Match.',
            'only_in_cor' => $result['only_in_cor'], // optional, pwedeng di mo gamitin sa UI
            'only_in_cog' => $result['only_in_cog'],
        ], 200);
    }

    public function getCorOutputFile()
    {
        $abs = storage_path('app/cor/cor_output.txt');
        if (!is_file($abs)) {
            return response('')->header('Content-Type', 'text/plain');
        }

        return response(\Illuminate\Support\Facades\File::get($abs))
            ->header('Content-Type', 'text/plain');
    }

    public function logJsError(Request $r)
    {
        Log::warning('JS error', [
            'where' => $r->input('where'),
            'msg'   => $r->input('message'),
            'extra' => $r->all(),
        ]);
        return response()->json(['ok' => true]);
    }
}