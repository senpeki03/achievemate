<?php

namespace App\Http\Controllers\Registrar;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

use App\Models\Curriculum;
use App\Models\StudentManage;
use App\Models\Login;
use App\Models\StudentCourse; // store campus/college/program/major/curriculum per student
use App\Mail\StudentRegistrationMail;

class StudentUploadController extends Controller
{
    public function showUploadForm(Request $request)
    {
        $curriculumId = $request->query('curriculum_id');
        $yearLevel    = $request->query('year_level');
        $academicYear = $request->query('academic_year');

        if (empty($curriculumId) || empty($yearLevel)) {
            return redirect()
                ->route('registrar.student')
                ->with('error', 'Please pick a Curriculum and Year Level first.');
        }

        $curriculum  = Curriculum::with('curriculumAy.program', 'curriculumAy.college')->findOrFail($curriculumId);
        $programName = $curriculum->curriculumAy->program->Abbreviation ?? 'N/A';
        $collegeName = $curriculum->curriculumAy->college->Abbreviation ?? 'N/A';

        return view('registrar.studentupload', compact(
            'curriculumId',
            'programName',
            'collegeName',
            'yearLevel',
            'academicYear'
        ));
    }

    public function uploadCSV(Request $request)
    {
        $request->validate([
            'csv_file'      => 'required|mimes:csv,txt|max:10240',
            'curriculum_id' => 'required|exists:curriculum,curriculum_id',
            'year_level'    => 'required|in:FIRST YEAR,SECOND YEAR,THIRD YEAR,FOURTH YEAR',
        ]);

        $file          = $request->file('csv_file');
        $curriculumId  = (int) $request->input('curriculum_id');
        $yearLevel     = (string) $request->input('year_level');
        $academicYear  = (string) ($request->input('academic_year') ?: $this->computeCurrentAY());

        // For StudentCourse
        $campusId  = $request->integer('campus_id') ?: null;
        $collegeId = $request->integer('college_id') ?: null;
        $programId = $request->integer('program_id') ?: null;
        $majorId   = $request->integer('major_id') ?: null;

        if (!$file->isValid()) {
            return back()->with('error', 'Invalid file upload.');
        }

        // Read CSV robustly
        $raw = file_get_contents($file->getRealPath());
        $raw = ltrim($raw, "\xEF\xBB\xBF");                      // strip BOM
        $raw = preg_replace("/\r\n|\r|\n/", "\n", $raw);         // normalize newlines
        $lines = array_values(array_filter(
            array_map('trim', explode("\n", $raw)),
            fn($l) => $l !== ''
        ));
        if (empty($lines)) {
            return back()->with('error', 'CSV file is empty.');
        }

        $firstLine = $lines[0];
        $delimiter = (substr_count($firstLine, ';') > substr_count($firstLine, ',')) ? ';' : ',';
        $rows = array_map(fn($line) => str_getcsv($line, $delimiter), $lines);

        $rawHeader  = $rows[0];
        $normalized = array_map(fn($h) => preg_replace('/[^A-Z]/', '', strtoupper(trim($h))), $rawHeader);

        $canonicalMap = [
            'SRCODE'     => 'SRCODE',
            'FIRSTNAME'  => 'FIRSTNAME',
            'MIDDLENAME' => 'MIDDLENAME',
            'LASTNAME'   => 'LASTNAME',
            'CONTACT'    => 'CONTACT',
            'EMAIL'      => 'EMAIL',
        ];

        $headerKeys = [];
        foreach ($normalized as $i => $norm) {
            $headerKeys[$i] = $canonicalMap[$norm] ?? null;
        }

        $required = array_values($canonicalMap);
        $present  = array_values(array_unique(array_filter($headerKeys)));
        $missing  = array_diff($required, $present);
        if (!empty($missing)) {
            return back()->with('error', 'Invalid CSV format. Missing: ' . implode(', ', $missing));
        }

        unset($rows[0]); // data only

        // -------- Prepare & normalize (Title Case + optional sort) --------
        $prepared = [];
        foreach ($rows as $row) {
            $data = [];
            foreach ($headerKeys as $colIndex => $key) {
                if ($key !== null) {
                    $data[$key] = isset($row[$colIndex]) ? trim($row[$colIndex]) : '';
                }
            }
            // kailangan may SRCODE at EMAIL
            if (empty($data['SRCODE']) || empty($data['EMAIL'])) {
                continue;
            }

            // Title-case names (handles multi-word, hyphen, apostrophe, particles)
            $data['FIRSTNAME']  = $this->titleCaseName($data['FIRSTNAME']  ?? '');
            $data['MIDDLENAME'] = $this->titleCaseName($data['MIDDLENAME'] ?? '');
            $data['LASTNAME']   = $this->titleCaseName($data['LASTNAME']   ?? '');

            $prepared[] = $data;
        }

        // Sort A→Z by Last, First, Middle (safe if you want deterministic inserts)
        usort($prepared, fn($a,$b) => $this->cmpName($a,$b));

        // -------- Insert loop --------
        $hasDuplicate         = false;
        $duplicateEmail       = false;
        $duplicateSrcode      = false;
        $duplicateEmailCount  = 0;
        $duplicateSrcodeCount = 0;
        $insertedCount        = 0;

        foreach ($prepared as $index => $data) {
            $srcode   = strtoupper($data['SRCODE']);
            $email    = $data['EMAIL'];
            $contact  = $data['CONTACT'] ?? '';
            $first    = $data['FIRSTNAME'];
            $middle   = $data['MIDDLENAME'];
            $last     = $data['LASTNAME'];
            $fullname = trim("$first $middle $last");
            $username = $srcode . '@g.batstate-u.edu.ph';

            try {
                // --- CHECK DUPLICATE BY EMAIL + CURRICULUM ---
                $existsEmail = StudentManage::where('Email', $email)
                    ->where('curriculum_id', $curriculumId)
                    ->exists();

                // --- CHECK DUPLICATE BY SRCODE + CURRICULUM ---
                $existsSrcode = StudentManage::where('SRCODE', $srcode)
                    ->where('curriculum_id', $curriculumId)
                    ->exists();

                if ($existsEmail || $existsSrcode) {
                    $hasDuplicate = true;

                    if ($existsEmail) {
                        $duplicateEmail = true;
                        $duplicateEmailCount++;
                        Log::info('Duplicate EMAIL detected during CSV upload', [
                            'email'         => $email,
                            'curriculum_id' => $curriculumId,
                            'row_index'     => $index,
                        ]);
                    }

                    if ($existsSrcode) {
                        $duplicateSrcode = true;
                        $duplicateSrcodeCount++;
                        Log::info('Duplicate SRCODE detected during CSV upload', [
                            'srcode'        => $srcode,
                            'curriculum_id' => $curriculumId,
                            'row_index'     => $index,
                        ]);
                    }

                    // skip insert
                    continue;
                }

                // ---- CREATE LOGIN (kung wala pa) ----
                $login = Login::firstOrCreate(
                    ['username' => $username],
                    ['password' => Hash::make($srcode), 'usertype' => 'Student']
                );

                // ---- INSERT STUDENT ----
                $student = StudentManage::create([
                    'Login_id'      => $login->Login_id,
                    'curriculum_id' => $curriculumId,
                    'SRCODE'        => $srcode,
                    'First_name'    => $first,
                    'Middle_name'   => $middle,
                    'Last_name'     => $last,
                    'Contact'       => $contact,
                    'Year'          => $yearLevel,
                    'Email'         => $email,
                    'Academic_year' => $academicYear,
                ]);

                // Ensure StudentCourse row is present/updated
                if ($student && $student->Student_id) {
                    StudentCourse::updateOrCreate(
                        [
                            'Student_id'    => $student->Student_id,
                            'curriculum_id' => $curriculumId,
                        ],
                        [
                            'Campus_id'  => $campusId,
                            'College_id' => $collegeId,
                            'Program_id' => $programId,
                            'Major_id'   => $majorId,
                        ]
                    );
                }

                // Fire email (best-effort)
                try {
                    Mail::to($email)->send(new StudentRegistrationMail([
                        'logo'     => asset('img/logo.png'),
                        'fullname' => $fullname,
                        'title'    => 'Mr./Ms.',
                        'loginUrl' => route('login'),
                        'username' => $username,
                        'password' => $srcode,
                        'usertype' => 'Student',
                    ]));
                } catch (\Throwable $mailEx) {
                    Log::warning('Registration mail failed', ['email' => $email, 'err' => $mailEx->getMessage()]);
                }

                $insertedCount++;
            } catch (\Throwable $e) {
                Log::error("Upload failed at row {$index}", ['row' => $data, 'error' => $e->getMessage()]);
            }
        }

        if ($insertedCount === 0 && !$hasDuplicate) {
            return back()->with('error', 'No students were inserted. Please check your CSV format or data.');
        }

        if ($hasDuplicate) {
            return back()->withInput()->with([
                'successAddModal'       => $insertedCount > 0,
                'duplicateEmail'        => $duplicateEmail,
                'duplicateEmailCount'   => $duplicateEmailCount,
                'duplicateSrcode'       => $duplicateSrcode,
                'duplicateSrcodeCount'  => $duplicateSrcodeCount,
            ]);
        }

        return redirect()
            ->route('registrar.studentlist', ['curriculum_id' => $curriculumId])
            ->with('successAddModal', true);
    }


    private function computeCurrentAY(): string
    {
        $now   = now('Asia/Manila');
        $year  = (int) $now->format('Y');
        $month = (int) $now->format('n');
        $start = ($month >= 8) ? $year : ($year - 1);
        return sprintf('%d-%d', $start, $start + 1);
    }

    /** -------- Helpers: Title Case + A→Z compare -------- */

    private function titleCaseName(?string $s): string
    {
        if (!$s) return '';
        $s = preg_replace('/\s+/', ' ', trim($s));
        // Title-case with delimiters
        $s = ucwords(strtolower($s), " -'");

        // Lowercase common particles unless first token
        $particles = ['de','del','dela','la','las','los','van','von','da','dos','di','du','le'];
        $parts = explode(' ', $s);
        foreach ($parts as $i => $p) {
            $pl = strtolower($p);
            if ($i > 0 && in_array($pl, $particles, true)) {
                $parts[$i] = $pl;
            }
            // O'Neil, D'Alessandro, etc.
            if (strpos($parts[$i], "'") !== false) {
                $parts[$i] = implode("'", array_map(
                    fn($seg) => $seg === '' ? '' : ucfirst(strtolower($seg)),
                    explode("'", $parts[$i])
                ));
            }
            // Optional Mc/Mac tweak
            if (preg_match('/^(Mc|Mac)([A-Za-z]+)/', $parts[$i], $m)) {
                $prefix = $m[1];
                $rest   = ucfirst(strtolower($m[2]));
                $parts[$i] = $prefix . $rest;
            }
        }
        return implode(' ', $parts);
    }

    private function cmpName(array $a, array $b): int
    {
        foreach (['LASTNAME','FIRSTNAME','MIDDLENAME'] as $k) {
            $aa = strtolower($a[$k] ?? '');
            $bb = strtolower($b[$k] ?? '');
            if ($aa === $bb) continue;
            return strnatcasecmp($aa, $bb);
        }
        return 0;
    }
}
