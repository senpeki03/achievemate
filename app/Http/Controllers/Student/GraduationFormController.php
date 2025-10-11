<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\GraduationForm;
use App\Models\StudentManage;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class GraduationFormController extends Controller
{
    public function show(Request $request)
    {
        // --- Resolve PDF template ---
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

        // --- Prefill from logged-in student + any previously saved graduation_form ---
        $loginId = optional($request->user())->Login_id;
        $student = $loginId ? StudentManage::where('Login_id', $loginId)->first() : null;

        $formRow = $student
            ? GraduationForm::where('Student_id', $student->Student_id)->first()
            : null;

        // Map DB -> UI field names (the JS expects these keys)
        $prefill = [
            // Student master data
            'surname'        => $student->Last_name   ?? '',
            'first_name'     => $student->First_name  ?? '',
            'middle_name'    => $student->Middle_name ?? '',
            'sr_code'        => $student->SRCODE      ?? '',
            'contact_number' => $student->Contact     ?? '',
            'email'          => $student->Email       ?? '',

            // Graduation form (if previously saved)
            'birthdate'         => optional($formRow?->Birthdate)->format('Y-m-d') ?: '',
            'place_of_birth'    => $formRow->PlaceofBirth   ?? '',
            'home_address'      => $formRow->HomeAddress    ?? '',
            'zip_code'          => $formRow->ZIP_Code       ?? '',
            'secondary_school'  => $formRow->Sec_Grad       ?? '',
            'secondary_year'    => $formRow->Sec_Grad_Year  ?? '',
            'elementary_school' => $formRow->Elem_Grad      ?? '',
            'elementary_year'   => $formRow->Elem_Grad_Year ?? '',
            // Address PSGC codes are optional in your UI; include if you later add columns
            'region_code'   => '',
            'region_name'   => '',
            'province_code' => '',
            'province_name' => '',
            'city_code'     => '',
            'city_name'     => '',
            'barangay_code' => '',
            'barangay_name' => '',
        ];

        // --- Full FIELD_MAP used by your Blade/JS ---
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

            // These will be replaced by a combined "Graduation Period" row by your JS
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
            // the rest (names/contact) live in student_manage, not saved here
        ]);

        $loginId = optional($request->user())->Login_id;
        $student = $loginId ? StudentManage::where('Login_id', $loginId)->first() : null;

        if (!$student) {
            return back()->withErrors(['auth' => 'No linked student found for this account.']);
        }

        $payload = [
            'Student_id'     => $student->Student_id,
            'Birthdate'      => self::toDateYmd($v['birthdate'] ?? null),
            'PlaceofBirth'   => $v['place_of_birth']    ?? null,
            'HomeAddress'    => $v['home_address']      ?? null,
            'ZIP_Code'       => $v['zip_code']          ?? null,
            'Sec_Grad'       => $v['secondary_school']  ?? null,
            'Sec_Grad_Year'  => $v['secondary_year']    ?? null,
            'Elem_Grad'      => $v['elementary_school'] ?? null,
            'Elem_Grad_Year' => $v['elementary_year']   ?? null,
        ];

        GraduationForm::updateOrCreate(
            ['Student_id' => $student->Student_id],
            $payload
        );

        return back()->with('ok', 'Application saved.');
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
