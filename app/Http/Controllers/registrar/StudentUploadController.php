<?php

namespace App\Http\Controllers\Registrar;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Curriculum;
use App\Models\StudentManage;
use App\Models\Login;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use App\Mail\StudentRegistrationMail;

class StudentUploadController extends Controller
{
    public function showUploadForm(Request $request)
    {
        $curriculumId = $request->query('curriculum_id');
        $yearLevel    = $request->query('year_level');

        // ✅ Redirect instead of aborting if params are missing
        if (empty($curriculumId) || empty($yearLevel)) {
            return redirect()
                ->route('registrar.student')
                ->with('error', 'Please pick a Curriculum and Year Level first.');
        }

        $curriculum  = Curriculum::with('curriculumAy.program', 'curriculumAy.college')->findOrFail($curriculumId);
        $programName = $curriculum->curriculumAy->program->Abbreviation ?? 'N/A';
        $collegeName = $curriculum->curriculumAy->college->Abbreviation ?? 'N/A';

        return view('registrar.studentupload', compact('curriculumId', 'programName', 'collegeName', 'yearLevel'));
    }

    public function uploadCSV(Request $request)
    {
        $request->validate([
            'csv_file'      => 'required|mimes:csv,txt|max:10240',
            'curriculum_id' => 'required|exists:curriculum,Curriculum_id',
            'year_level'    => 'required|in:FIRST YEAR,SECOND YEAR,THIRD YEAR,FOURTH YEAR',
        ]);

        $file         = $request->file('csv_file');
        $curriculumId = (int) $request->input('curriculum_id');
        $yearLevel    = $request->input('year_level');

        if (!$file->isValid()) {
            return back()->with('error', 'Invalid file upload.');
        }

        // ---- robust CSV read (BOM + , or ;) ----
        $raw = file_get_contents($file->getRealPath());
        $raw = ltrim($raw, "\xEF\xBB\xBF");                 // remove UTF-8 BOM
        $raw = preg_replace("/\r\n|\r|\n/", "\n", $raw);    // normalize newlines
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
        // -----------------------------------------

        $rawHeader  = $rows[0];

        // normalize headers: uppercase + remove non-letters (handles FIRST_NAME, First Name, etc.)
        $normalized = array_map(function ($h) {
            return preg_replace('/[^A-Z]/', '', strtoupper(trim($h)));
        }, $rawHeader);

        // map normalized header -> canonical keys used in code
        $canonicalMap = [
            'SRCODE'     => 'SRCODE',
            'FIRSTNAME'  => 'FIRSTNAME',
            'MIDDLENAME' => 'MIDDLENAME',
            'LASTNAME'   => 'LASTNAME',
            'CONTACT'    => 'CONTACT',
            'EMAIL'      => 'EMAIL',
        ];

        // build per-column key list and verify required headers exist
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

        // data rows
        unset($rows[0]);

        $hasDuplicate   = false;
        $duplicateCount = 0;
        $insertedCount  = 0;

        foreach ($rows as $index => $row) {
            // build $data using canonical keys
            $data = [];
            foreach ($headerKeys as $colIndex => $key) {
                if ($key !== null) {
                    $data[$key] = isset($row[$colIndex]) ? trim($row[$colIndex]) : '';
                }
            }

            // basic sanity
            if (empty($data['SRCODE']) || empty($data['EMAIL'])) {
                Log::warning("Row {$index} skipped: missing SRCODE or EMAIL.", ['row' => $row]);
                continue;
            }

            $srcode   = strtoupper($data['SRCODE']);
            $email    = $data['EMAIL'];
            $contact  = $data['CONTACT'] ?? '';
            $fullname = trim(($data['FIRSTNAME'] ?? '') . ' ' . ($data['MIDDLENAME'] ?? '') . ' ' . ($data['LASTNAME'] ?? ''));
            $username = $srcode . '@g.batstate-u.edu.ph';

            try {
                // ✅ always get a Login_id (don’t skip the student if the login exists)
                $login = Login::firstOrCreate(
                    ['username' => $username],
                    ['password' => Hash::make($srcode), 'usertype' => 'Student']
                );

                // handle duplicate student for same curriculum/email (skip or update—here we skip)
                $exists = StudentManage::where('Email', $email)
                    ->where('curriculum_id', $curriculumId)
                    ->exists();

                if ($exists) {
                    $hasDuplicate = true;
                    $duplicateCount++;
                    continue;
                }

                StudentManage::create([
                    'Login_id'      => $login->Login_id,
                    'curriculum_id' => $curriculumId,
                    'SRCODE'        => $srcode,
                    'First_name'    => $data['FIRSTNAME'] ?? '',
                    'Middle_name'   => $data['MIDDLENAME'] ?? '',
                    'Last_name'     => $data['LASTNAME'] ?? '',
                    'Contact'       => $contact,
                    'Year'          => $yearLevel,
                    'Email'         => $email,
                ]);

                // email is optional; wrap in try so a mail failure doesn’t block inserts
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
                'successAddModal' => $insertedCount > 0,
                'duplicateEmail'  => true,
                'duplicateCount'  => $duplicateCount
            ]);
        }

        return redirect()
            ->route('registrar.studentlist', ['curriculum_id' => $curriculumId])
            ->with('successAddModal', true);
    }
}
