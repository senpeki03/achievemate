<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\GraduationForm;
use App\Models\StudentManage;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Smalot\PdfParser\Parser;

class GraduationFormController extends Controller
{
    /** Absolute Windows paths (kept) */
    private string $cogDir = 'C:\\xampp\\htdocs\\Capstone\\AchieveMate\\storage\\graduation\\cog';
    private string $corDir = 'C:\\xampp\\htdocs\\Capstone\\AchieveMate\\storage\\graduation\\cor';

    public function show(Request $request)
    {
        // ---- PDF template (unchanged) ----
        $baseDir  = 'pdf_templates';
        $expected = 'BatStateU-FO-REG-10_Application for Graduation_Rev. 02.pdf';

        if (!Storage::disk('local')->exists($baseDir)) {
            Storage::disk('local')->makeDirectory($baseDir);
        }

        $templatePath = Storage::disk('local')->exists($baseDir . '/' . $expected)
            ? ($baseDir . '/' . $expected)
            : (collect(Storage::disk('local')->allFiles($baseDir))
                ->first(fn ($p) => str_ends_with(strtolower($p), '.pdf')
                    && str_contains(strtolower($p), 'application for graduation')));

        $pdfUrl = $templatePath ? route('media', ['path' => ltrim($templatePath, '/')]) : null;

        // ---- Prefill from user/student (unchanged) ----
        $loginId = optional($request->user())->Login_id;
        $student = $loginId ? StudentManage::where('Login_id', $loginId)->first() : null;

        $formRow = $student
            ? GraduationForm::where('Student_id', $student->Student_id)->first()
            : null;

        $prefill = [
            'surname'        => $student->Last_name   ?? '',
            'first_name'     => $student->First_name  ?? '',
            'middle_name'    => $student->Middle_name ?? '',
            'sr_code'        => $student->SRCODE      ?? '',
            'contact_number' => $student->Contact     ?? '',
            'email'          => $student->Email       ?? '',

            'birthdate'         => optional($formRow?->Birthdate)->format('Y-m-d') ?: '',
            'place_of_birth'    => $formRow->PlaceofBirth   ?? '',
            'home_address'      => $formRow->HomeAddress    ?? '',
            'zip_code'          => $formRow->ZIP_Code       ?? '',
            'secondary_school'  => $formRow->Sec_Grad       ?? '',
            'secondary_year'    => $formRow->Sec_Grad_Year  ?? '',
            'elementary_school' => $formRow->Elem_Grad      ?? '',
            'elementary_year'   => $formRow->Elem_Grad_Year ?? '',

            // PSGC placeholders
            'region_code'   => '',
            'region_name'   => '',
            'province_code' => '',
            'province_name' => '',
            'city_code'     => '',
            'city_name'     => '',
            'barangay_code' => '',
            'barangay_name' => '',
        ];

        $fields = [
            'surname'        => ['t'=>13.6, 'l'=>1.6,  'w'=>22.5, 'type'=>'text',     'ph'=>'SURNAME'],
            'first_name'     => ['t'=>13.6, 'l'=>24.7, 'w'=>22.5, 'type'=>'text',     'ph'=>'FIRST NAME'],
            'middle_name'    => ['t'=>13.6, 'l'=>47.8, 'w'=>22.5, 'type'=>'text',     'ph'=>'MIDDLE NAME'],
            'extension_name' => ['t'=>13.6, 'l'=>70.9, 'w'=>27.5, 'type'=>'text',     'ph'=>'EXT. (JR/SR/etc)'],

            'sr_code'        => ['t'=>20.9, 'l'=>1.6,  'w'=>14.6, 'type'=>'text',     'ph'=>'SR CODE'],
            'birthdate'      => ['t'=>20.9, 'l'=>16.6, 'w'=>18.0, 'type'=>'text',     'ph'=>'MM/DD/YYYY'],
            'place_of_birth' => ['t'=>20.9, 'l'=>35.1, 'w'=>28.2, 'type'=>'text',     'ph'=>'PLACE OF BIRTH'],

            'home_address'   => ['t'=>27.6, 'l'=>1.6,  'w'=>47.8, 'type'=>'textarea', 'rows'=>3,'ph'=>'HOME ADDRESS', 'h'=>8],
            'zip_code'       => ['t'=>27.6, 'l'=>50.1, 'w'=>10.0, 'type'=>'text',     'ph'=>'ZIP'],
            'contact_number' => ['t'=>31.3, 'l'=>50.1, 'w'=>25.0, 'type'=>'text',     'ph'=>'CONTACT NUMBER'],
            'email'          => ['t'=>35.1, 'l'=>50.1, 'w'=>25.0, 'type'=>'email',    'ph'=>'EMAIL ADDRESS'],

            'secondary_school' => ['t'=>41.5, 'l'=>1.6,  'w'=>47.8, 'type'=>'text',   'ph'=>'SECONDARY SCHOOL GRADUATED'],
            'secondary_year'   => ['t'=>41.5, 'l'=>50.1, 'w'=>10.0, 'type'=>'text',   'ph'=>'YEAR'],
            'elementary_school'=> ['t'=>46.0, 'l'=>1.6,  'w'=>47.8, 'type'=>'text',   'ph'=>'ELEMENTARY SCHOOL GRADUATED'],
            'elementary_year'  => ['t'=>46.0, 'l'=>50.1, 'w'=>10.0, 'type'=>'text',   'ph'=>'YEAR'],

            'grad_dec'       => ['t'=>52.0, 'l'=>18.8, 'w'=>3.0,  'type'=>'checkbox'],
            'grad_dec_year'  => ['t'=>52.0, 'l'=>27.0, 'w'=>9.0,  'type'=>'text'],
            'grad_may'       => ['t'=>52.0, 'l'=>42.0, 'w'=>3.0,  'type'=>'checkbox'],
            'grad_may_year'  => ['t'=>52.0, 'l'=>48.5, 'w'=>9.0,  'type'=>'text'],
            'grad_mid'       => ['t'=>52.0, 'l'=>64.0, 'w'=>3.0,  'type'=>'checkbox'],
            'grad_mid_year'  => ['t'=>52.0, 'l'=>74.0, 'w'=>9.0,  'type'=>'text'],

            'college'        => ['t'=>56.5, 'l'=>1.6,  'w'=>47.8, 'type'=>'text',     'ph'=>'COLLEGE'],
            'program'        => ['t'=>60.4, 'l'=>1.6,  'w'=>47.8, 'type'=>'text',     'ph'=>'PROGRAM'],
            'major'          => ['t'=>64.3, 'l'=>1.6,  'w'=>47.8, 'type'=>'text',     'ph'=>'MAJOR'],
        ];

        return view('student.graduationform', compact('pdfUrl', 'fields', 'prefill'));
    }

    public function store(Request $request)
    {
        $v = $request->validate([
            'birthdate'          => 'nullable|string|max:20',
            'place_of_birth'     => 'nullable|string|max:150',
            'home_address'       => 'nullable|string|max:600',
            'zip_code'           => 'nullable|string|max:10',
            'secondary_school'   => 'nullable|string|max:200',
            'secondary_year'     => 'nullable|string|max:10',
            'elementary_school'  => 'nullable|string|max:200',
            'elementary_year'    => 'nullable|string|max:10',
        ]);

        $loginId = optional($request->user())->Login_id;
        $student = $loginId ? StudentManage::where('Login_id', $loginId)->first() : null;

        if (!$student) {
            return back()->withErrors(['auth' => 'No linked student found for this account.']);
        }

        GraduationForm::updateOrCreate(
            ['Student_id' => $student->Student_id],
            [
                'Student_id'     => $student->Student_id,
                'Birtdate'       => self::toDateYmd($v['birthdate'] ?? null), // DB typo kept
                'PlaceofBirth'   => $v['place_of_birth']    ?? null,
                'HomeAddress'    => $v['home_address']      ?? null,
                'ZIP_Code'       => $v['zip_code']          ?? null,
                'Sec_Grad'       => $v['secondary_school']  ?? null,
                'Sec_Grad_Year'  => $v['secondary_year']    ?? null,
                'Elem_Grad'      => $v['elementary_school'] ?? null,
                'Elem_Grad_Year' => $v['elementary_year']   ?? null,
            ]
        );

        return back()->with('ok', 'Application saved.');
    }

    /**
     * COR upload (PDF only) — field name must be "cor"
     * Saves to ...\graduation\cor\cor_uploaded.pdf + cor_extracted.txt
     * Returns the absolute paths and page_count.
     */
    public function uploadCor(Request $request)
    {
        $request->validate([
            'cor' => ['required', 'file', 'mimetypes:application/pdf', 'max:51200'],
        ], ['mimetypes' => 'The file must be a PDF.']);

        File::ensureDirectoryExists($this->corDir, 0755, true);

        $pdfPath = rtrim($this->corDir, '\\/') . DIRECTORY_SEPARATOR . 'cor_uploaded.pdf';
        $txtPath = rtrim($this->corDir, '\\/') . DIRECTORY_SEPARATOR . 'cor_extracted.txt';

        // move uploaded file → overwrite
        /** @var \Illuminate\Http\UploadedFile $file */
        $file = $request->file('cor');
        @unlink($pdfPath);
        $file->move($this->corDir, 'cor_uploaded.pdf');

        // extract text + page count
        [$extracted, $pages] = $this->extractPdfTextAndCount($pdfPath);

        // save text alongside
        @File::put($txtPath, $extracted);

        return response()->json([
            'ok'            => true,
            'page_count'    => $pages,
            // keys your Blade already reads:
            'cor_pdf_path'  => $pdfPath,
            'cor_text_path' => $txtPath,
            // optional generic keys
            'pdf_path'      => $pdfPath,
            'txt_path'      => $txtPath,
            // no preview URL in this Windows-path setup
            'cor_png_url'   => null,
        ]);
    }

    /**
     * COG upload (PDF only) — accepts "grades_pdf" (preferred), or "pdf" / "cog"
     * Saves to ...\graduation\cog\cog_uploaded.pdf + cog_extracted.txt
     * Returns absolute paths + page_count.
     */
    public function uploadCog(Request $request)
    {
        $field = $request->hasFile('grades_pdf') ? 'grades_pdf'
               : ($request->hasFile('pdf') ? 'pdf' : 'cog');

        $request->validate([
            $field => ['required', 'file', 'mimetypes:application/pdf', 'max:51200'],
        ], ['mimetypes' => 'The file must be a PDF.']);

        File::ensureDirectoryExists($this->cogDir, 0755, true);

        $pdfPath = rtrim($this->cogDir, '\\/') . DIRECTORY_SEPARATOR . 'cog_uploaded.pdf';
        $txtPath = rtrim($this->cogDir, '\\/') . DIRECTORY_SEPARATOR . 'cog_extracted.txt';

        /** @var \Illuminate\Http\UploadedFile $file */
        $file = $request->file($field);
        @unlink($pdfPath);
        $file->move($this->cogDir, 'cog_uploaded.pdf');

        // extract text + page count
        [$extracted, $pages] = $this->extractPdfTextAndCount($pdfPath);

        // save text alongside
        @File::put($txtPath, $extracted);

        return response()->json([
            'ok'               => true,
            'page_count'       => $pages,
            // names used by your JS:
            'grades_pdf_path'  => $pdfPath,
            'grades_text_path' => $txtPath,
            // aliases (some parts of your JS use "cog_*"):
            'cog_pdf_path'     => $pdfPath,
            // generic
            'pdf_path'         => $pdfPath,
            'txt_path'         => $txtPath,
            // no preview URL (local path)
            'grades_pdf_url'   => null,
        ]);
    }

    /**
     * Optional: endpoints to overwrite the extracted .txt on demand
     * (If your Blade still POSTs to route-save-*-output, these keep working
     *  and always write the fixed filenames.)
     */
    public function saveCorText(Request $request)
    {
        $request->validate(['raw_text' => ['required','string']]);

        File::ensureDirectoryExists($this->corDir, 0755, true);
        $txtPath = rtrim($this->corDir, '\\/') . DIRECTORY_SEPARATOR . 'cor_extracted.txt';
        @File::put($txtPath, (string) $request->input('raw_text'));

        return response()->json([
            'ok'      => true,
            'path'    => $txtPath,
            'message' => 'COR text saved',
        ]);
    }

    public function saveCogText(Request $request)
    {
        // Accept either a single text blob or structured fields
        $payload = $request->has('cog_raw')
            ? (string) $request->input('cog_raw')
            : json_encode($request->all(), JSON_PRETTY_PRINT);

        File::ensureDirectoryExists($this->cogDir, 0755, true);
        $txtPath = rtrim($this->cogDir, '\\/') . DIRECTORY_SEPARATOR . 'cog_extracted.txt';
        @File::put($txtPath, $payload);

        return response()->json([
            'ok'      => true,
            'path'    => $txtPath,
            'message' => 'COG text saved',
        ]);
    }

    /**
     * Helper: parse PDF text and count pages using smalot/pdfparser.
     */
    private function extractPdfTextAndCount(string $absolutePdfPath): array
    {
        $extracted = '';
        $pages = 0;

        try {
            $parser = new Parser();
            $pdf    = $parser->parseFile($absolutePdfPath);

            $extracted = trim($pdf->getText() ?? '');
            $pages     = is_array($pdf->getPages()) ? count($pdf->getPages()) : 0;
        } catch (\Throwable $e) {
            // keep defaults (empty text, 0 pages)
        }

        return [$extracted, $pages];
    }

    private static function toDateYmd(?string $raw): ?string
    {
        if (!$raw) return null;
        foreach (['Y-m-d','m/d/Y','m-d-Y','d/m/Y','d-m-Y','M d, Y','d M Y','Y/m/d'] as $fmt) {
            try {
                $c = Carbon::createFromFormat($fmt, trim($raw));
                if ($c !== false) return $c->format('Y-m-d');
            } catch (\Throwable) {}
        }
        try { return Carbon::parse($raw)->format('Y-m-d'); } catch (\Throwable) { return null; }
    }
}
