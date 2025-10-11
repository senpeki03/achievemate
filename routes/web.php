<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

//ADMIN
use App\Http\Controllers\admin\UserListController;
use App\Http\Controllers\admin\UserImportController;
use App\Http\Controllers\admin\SystemLogsController;
use App\Http\Controllers\Admin\CampusController;
use App\Http\Controllers\Admin\CollegeController;
use App\Http\Controllers\Admin\ProgramController;
use App\Http\Controllers\Admin\MajorController;
use App\Http\Controllers\Admin\UserManageController;
use App\Http\Controllers\Admin\DesignationController;
use App\Http\Controllers\Admin\UserDesignationController;  


//REGISTRAR
use App\Http\Controllers\Registrar\Controller;
use App\Http\Controllers\Registrar\StudentImportController;
use App\Http\Controllers\Registrar\ListController; // ✅ Added missing import
use App\Http\Controllers\Registrar\DashboardController;
use App\Http\Controllers\Registrar\DashboardRegistrarController;
use App\Http\Controllers\Registrar\StudentUploadController;
use App\Http\Controllers\Registrar\StudentController;
use App\Http\Controllers\Registrar\StudentManageListController;
use App\Http\Controllers\Registrar\StudentListController;


//STUDENT
use App\Http\Controllers\Student\DashboardStudentController;
use App\Http\Controllers\Student\NewStudentController;
use App\Http\Controllers\Student\ApplicationController;
use App\Http\Controllers\Student\StudentApplicationController;
use App\Http\Controllers\Student\ApplicationStatusController;
use App\Http\Controllers\Student\ApplicationRedirectController;
use App\Http\Controllers\Student\PdfUploadController;
use App\Http\Controllers\Student\NotificationController; 
use App\Http\Controllers\Student\PortfolioController; 
use App\Http\Controllers\Student\CertificateController;
use App\Http\Controllers\Student\ExtractedController;
use App\Http\Controllers\Student\AttachmentsController;
use App\Http\Controllers\Student\PdfController;
use App\Http\Controllers\Student\CogDebugController;
use App\Http\Controllers\Student\QrResolverController;
use App\Http\Controllers\Student\CogUploadController;
use App\Http\Controllers\Student\StudentCurriculumController;
use App\Http\Controllers\Student\StudentPdfController;
use App\Http\Controllers\Student\GraduationFormController;
use App\Http\Middleware\VerifyCsrfToken;

//PROGRAM CHAIRPERSON

use App\Http\Controllers\Programchair\DeansHonorListController;
use App\Http\Controllers\Programchair\ProgramchairController;
use App\Http\Controllers\Programchair\PostController;
use App\Http\Controllers\Programchair\CurriculumController;
use App\Http\Controllers\Programchair\CurriculumCollegeController; 
use App\Http\Controllers\Programchair\DashboardProgramchairController;
use App\Http\Controllers\Programchair\RankController;



//DEAN
use App\Http\Controllers\Dean\DeanSidebarController;
use App\Http\Controllers\Dean\HonorListController;
use App\Http\Controllers\Dean\ApplicationNotifController;

// Authentication
Route::post('/signup', [AuthController::class, 'register'])->name('register.submit');
Route::post('/signin', [AuthController::class, 'login'])->name('login.submit');
Route::get('/logout', [AuthController::class, 'logout'])->name('logout');


// admin
Route::delete('/students/{id}', [UserListController::class, 'destroy'])->name('students.destroy');
Route::get('/download-template', [UserImportController::class, 'downloadTemplate'])->name('user.template');
Route::post('/import-user', [UserImportController::class, 'import'])->name('users.import');
Route::get('/userlist', [UserListController::class, 'index'])->name('admin.user.list');
Route::get('/admin/college/{department}', [UserListController::class, 'showCollegeList'])->name('admin.collegeuser.list');
Route::get('/admin/proflist/{usertype}', [UserListController::class, 'showUserTypeList'])->name('admin.proflist');
Route::get('/email-status', fn() => response()->json(['sent' => session('email_sent', false)]));
Route::get('/reset-email-status', fn() => session()->forget('email_sent'));
Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/userlist', [UserListController::class, 'index'])->name('user.list');
    Route::get('/college/{department}', [UserListController::class, 'showYearList'])->name('year.list');
    Route::get('/college/{department}/year/{year}', [UserListController::class, 'showStudentsByYear'])->name('users.by.year');
    Route::get('/proflist/{usertype}', [UserListController::class, 'showUserTypeList'])->name('proflist');
    Route::get('/college/{department}/students', [UserListController::class, 'showCollegeList'])->name('collegeuser.list');
});
Route::get('/admin/systemlogs', [SystemLogsController::class, 'index'])->name('admin.systemlogs');
Route::get('/admin/students/direct/{department}/{year}', [UserListController::class, 'showStudentsDirectly'])->name('admin.students.by.year.direct');
Route::get('/admin/students/{department}/{year}/{track}', [UserListController::class, 'showStudentsByTrack'])
    ->name('admin.students.by.track');


Route::middleware(['web'])->group(function () {
    Route::get('/admin/campus', [CampusController::class, 'index'])->name('admin.campus');
    Route::post('/admin/campus/store', [CampusController::class, 'store'])->name('admin.campus.store');
    Route::put('/admin/campus/update/{id}', [CampusController::class, 'update'])->name('admin.campus.update');
    Route::delete('/admin/campus/delete/{id}', [CampusController::class, 'destroy'])->name('admin.campus.delete');
});

Route::middleware(['web'])->group(function () {
    Route::get('/admin/college', [CollegeController::class, 'index'])->name('admin.college');
    Route::post('/admin/college/store', [CollegeController::class, 'store'])->name('admin.college.store');
    Route::put('/admin/college/update/{id}', [CollegeController::class, 'update'])->name('admin.college.update');
    Route::delete('/admin/college/delete/{id}', [CollegeController::class, 'destroy'])->name('admin.college.delete');
});

Route::middleware(['web'])->group(function () {
    Route::get('/admin/program', [ProgramController::class, 'index'])->name('admin.program');
    Route::post('/admin/program/store', [ProgramController::class, 'store'])->name('admin.program.store');
    Route::put('/admin/program/update/{id}', [ProgramController::class, 'update'])->name('admin.program.update');
    Route::delete('/admin/program/delete/{id}', [ProgramController::class, 'destroy'])->name('admin.program.delete');
});

Route::middleware(['web'])->group(function () {
    Route::get('/admin/major', [MajorController::class, 'index'])->name('admin.major');
    Route::post('/admin/major/store', [MajorController::class, 'store'])->name('admin.major.store');
    Route::put('/admin/major/update/{id}', [MajorController::class, 'update'])->name('admin.major.update');
    Route::delete('/admin/major/delete/{id}', [MajorController::class, 'destroy'])->name('admin.major.delete');
});
    
Route::middleware(['web'])->group(function () {
    Route::get('/admin/usermanage', [UserManageController::class, 'index'])->name('admin.usermanage');
    Route::post('/admin/usermanage/store', [UserManageController::class, 'store'])->name('admin.usermanage.store');
    Route::put('/admin/usermanage/update/{id}', [UserManageController::class, 'update'])->name('admin.usermanage.update');
    Route::delete('/admin/usermanage/delete/{id}', [UserManageController::class, 'destroy'])->name('admin.usermanage.delete');
});

Route::middleware(['web'])->group(function () {
    Route::get('/admin/designation', [DesignationController::class, 'index'])->name('admin.designation');
    Route::post('/admin/designation/store', [DesignationController::class, 'store'])->name('designation.store');
    Route::put('/admin/designation/update/{id}', [DesignationController::class, 'update'])->name('admin.designation.update');
    Route::delete('/admin/designation/delete/{id}', [DesignationController::class, 'destroy'])->name('admin.designation.delete');
});

Route::middleware(['web'])->group(function () {
    Route::get('/admin/userdesignation/{id}', [UserDesignationController::class, 'show'])->name('admin.userdesignation');
    Route::post('/admin/userdesignation/store', [UserDesignationController::class, 'store'])->name('admin.userdesignation.store');
    Route::delete('/admin/userdesignation/delete/{id}', [UserDesignationController::class, 'delete'])->name('admin.userdesignation.delete');
    Route::get('/admin/colleges/by-campus', [UserDesignationController::class, 'getCollegesByCampus']);
    Route::get('/admin/programs/by-college', [UserDesignationController::class, 'getByCollege']);
    Route::get('/admin/majors/by-program', [UserDesignationController::class, 'getByProgram']); // ✅ NEW
});







// registrar
Route::delete('/students/{id}', [StudentListController::class, 'destroy'])->name('students.destroy');
Route::put('/students/{id}', [ListController::class, 'update']);
Route::get('/college/{department}', [ListController::class, 'showYearList'])->name('registrar.year.list');
Route::get('/college/{department}/year', [ListController::class, 'showYearList'])->name('registrar.yearlist');
Route::get('/registrar/yearlist', [ListController::class, 'yearList'])->name('registrar.yearlist');
Route::get('/registrar/college', [ListController::class, 'collegeList'])->name('registrar.studentlist');
Route::get('/college/{department}/year/{year}', [ListController::class, 'showStudentsByYear'])->name('registrar.students.by.year');
Route::get('/college/{department}/year/{year}/track', [ListController::class, 'showTrackList'])->name('registrar.track');
Route::get('/college/{department}/year/{year}/track/{track}', [ListController::class, 'showStudentsByTrack'])->name('registrar.students.by.track');
Route::get('/students/create', [StudentImportController::class, 'create'])->name('students.create');
Route::post('/students', [StudentImportController::class, 'store'])->name('students.store');
Route::get('/registrar/studentmanagement/add', [StudentImportController::class, 'create'])->name('students.create');
Route::post('/registrar/studentmanagement/store', [StudentImportController::class, 'store'])->name('students.store');
Route::get('/check-email', function (\Illuminate\Http\Request $request) {
    $exists = \App\Models\Student::where('email', $request->email)->exists();
    return response()->json(['exists' => $exists]);
});
    Route::get('/registrar/dashboard', [DashboardRegistrarController::class, 'index'])->name('registrar.dashboard');
Route::middleware(['web'])->group(function () {
    Route::get('/registrar/student', [StudentController::class, 'index'])->name('registrar.student');
    Route::get('/registrar/studentlist', [StudentController::class, 'studentList'])->name('registrar.studentlist');
    Route::post('/registrar/student/assign-year', [StudentController::class, 'assignYear'])->name('student.assign.year');

    // ✅ Fix here
    Route::delete('/registrar/studentlist/delete/{id}', [StudentListController::class, 'destroy'])->name('registrar.studentlist.delete');
});

    

Route::middleware(['web'])->group(function () {
    Route::get('/registrar/studentupload', [StudentUploadController::class, 'showUploadForm'])->name('registrar.studentupload');
    Route::post('/registrar/student/upload-csv', [StudentUploadController::class, 'uploadCSV'])->name('student.upload.csv');

    // ✅ Move this line here inside the middleware group
    Route::get('/download-template', function () {
        $headers = [
            "Content-type" => "text/csv",
            "Content-Disposition" => "attachment; filename=studentmanage_template.csv",
            "Pragma" => "no-cache",
            "Cache-Control" => "must-revalidate, post-check=0, pre-check=0",
            "Expires" => "0"
        ];

        $columns = ['SRCODE', 'FIRSTNAME', 'MIDDLENAME', 'LASTNAME', 'CONTACT', 'EMAIL'];

        $callback = function() use ($columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    })->name('student.template.download');
});


Route::middleware(['web', 'auth'])
    ->prefix('student')
    ->name('student.')
    ->group(function () {

        // Dashboard
        Route::get('/dashboard', [DashboardStudentController::class, 'index'])
            ->name('dashboard');

        // Notifications
        Route::controller(NotificationController::class)->group(function () {
            Route::get('/notifications', 'showNotifications')->name('notifications');
            Route::post('/notifications/markAsRead', 'markAsRead')->name('notifications.markAsRead');
        });

        // Application (isang GET lang!)
        Route::get('/application', [ApplicationController::class, 'showApplication'])
            ->name('application'); // <- dito tumatakbo ang redirect logic mo

        // Final submit
        Route::post('/application/submit', [ApplicationController::class, 'submitApplication'])
            ->name('application.submit');

        Route::post('/application', [ApplicationController::class, 'store'])->name('application.submit');


        // PDF generators
        Route::post('/pdf/generate', [ApplicationController::class, 'regenerateDeanListForm'])
            ->name('pdf.generate'); // intentionally 410
        Route::post('/pdf/generate-json', [ApplicationController::class, 'generateDeanListFormFromJson'])
            ->name('pdf.generate.json');

        // QR resolve
        Route::post('/qr/resolve', [ApplicationController::class, 'resolveQr'])
            ->name('qr.resolve');

        // Uploads
        Route::post('/cor/upload', [PdfUploadController::class, 'upload'])->name('cor.upload');
        Route::post('/cog/upload', [CogUploadController::class, 'upload'])->name('cog.upload');


        // Attachments / debug sinks
        Route::post('/upload-attachments', [ApplicationController::class, 'uploadAttachments'])->name('upload.attachments');
        Route::post('/attachments', [AttachmentsController::class, 'store'])->name('attachments.store');
        Route::post('/save-cor-output', [ApplicationController::class, 'saveCorOutput'])->name('save-cor-output');
        Route::post('/save-cog-debug',  [ApplicationController::class, 'saveCogDebug'])->name('save-cog-debug');
        Route::post('/save-cog-output', [ApplicationController::class, 'saveCogOutput'])->name('save-cog-output');
        Route::post('/js-error',        [ApplicationController::class, 'logJsError'])->name('log-js-error');

                // Curriculum subjects
        Route::post('/curriculum/subjects', [StudentCurriculumController::class, 'subjects'])
            ->name('curriculum.subjects');
            
        // Application status
        Route::get('/applicationstatus', [ApplicationStatusController::class, 'index'])
            ->name('application.status');
        Route::delete('/application/{id}', [ApplicationStatusController::class, 'destroy'])
            ->name('application.destroy');

        // Portfolio (typo fix: /portfolio, hindi /profolio)
        Route::get('/portfolio', [PortfolioController::class, 'index'])->name('portfolio');

        // Certificates
        Route::get('/cert/deans/{application}', [CertificateController::class, 'preview'])->name('cert.deans.preview');
        Route::get('/cert/deans/{application}/download', [CertificateController::class, 'download'])->name('cert.deans.download');


        Route::get('/graduation-form',  [GraduationFormController::class, 'show'])->name('graduation.show');
        Route::post('/graduation-form', [GraduationFormController::class, 'store'])->name('graduation.store');

        Route::post('/cog/validate-from-text', [ApplicationController::class, 'validateCogFromText'])->name('cog.validateFromText');
    });

    Route::get('/media/{path}', function (string $path) {
        $clean = ltrim(str_replace('\\','/',$path), '/');

        $diskAbs   = Storage::disk('public')->path($clean);
        $symlinked = public_path('storage/'.$clean);
        $altAbs    = base_path('storage/app/public/'.$clean); // same as $diskAbs, just to compare

        $found = null;

        if (is_file($diskAbs))   $found = $diskAbs;
        elseif (is_file($symlinked)) $found = $symlinked;
        elseif (is_file($altAbs))    $found = $altAbs;

        if (!$found) {
            Log::warning('MEDIA 404', [
                'request'   => $clean,
                'diskAbs'   => $diskAbs,
                'symlinked' => $symlinked,
                'altAbs'    => $altAbs,
                'exists'    => [
                    'diskAbs'   => is_file($diskAbs),
                    'symlinked' => is_file($symlinked),
                    'altAbs'    => is_file($altAbs),
                ],
            ]);
            abort(404);
        }

        return response()->file($found);
    })->where('path', '.*')->name('media');
    // routes/web.php
use App\Http\Controllers\Dean\DeanApplicationController;
use App\Http\Controllers\Student\AwardClaimController;

Route::middleware(['auth', 'role:dean'])->group(function () {
    Route::post('/dean/application/update-status', [DeanApplicationController::class, 'updateStatus'])
        ->name('dean.application.update-status');
    Route::post('/dean/application/bulk-approve', [DeanApplicationController::class, 'bulkApprove'])
        ->name('dean.application.bulk-approve');
});

Route::get('/awards/claim/{token}', [\App\Http\Controllers\Student\AwardClaimController::class, 'claim'])
    ->middleware(['web','auth'])
    ->name('student.award.claim');

Route::get('/student/certificates/{applicationId}/png', [CertificateController::class, 'generatePng'])
    ->name('student.certificates.png');

Route::get('/phpinfo', function () { phpinfo(); });




use App\Http\Controllers\Student\GraduationApplicationController;
use App\Http\Controllers\Student\GraduationApplicationController as RsoGraduationController;
// ^ use the same controller as an alias, OR point to the real RSO controller if you have one


Route::middleware(['auth'])->group(function () {

    // Student-facing Graduation module
    Route::get('/student/apply',                  [GraduationApplicationController::class, 'create'])
        ->name('student.apply');

    Route::post('/student/graduation/apply',      [GraduationApplicationController::class, 'store'])
        ->name('student.graduation.store');

    Route::get('/student/graduation/my-application', [GraduationApplicationController::class, 'show'])
        ->name('student.graduation.application'); // different name

    Route::post('/student/graduation/submit',     [GraduationApplicationController::class, 'submit'])
        ->name('student.graduation.submit');

    Route::post('/student/graduation/resubmit',   [GraduationApplicationController::class, 'resubmit'])
        ->name('student.graduation.resubmit');

    // upload single requirement (route-model-bound)
    Route::post('/student/graduation/upload/{req}', [GraduationApplicationController::class, 'uploadRequirement'])
        ->whereNumber('req')
        ->name('student.graduation.upload');

    // RSO / Program Chair – add your gate/role middleware (e.g. can:rso)
    Route::middleware('can:manage-graduation')->group(function () {
        Route::get('/rso/graduation',                    [RsoGraduationController::class, 'rsoIndex'])
            ->name('rso.graduation.index');

        Route::get('/rso/graduation/{app}',              [RsoGraduationController::class, 'evaluate'])
            ->name('rso.graduation.evaluate');

        Route::post('/rso/graduation/{app}/initial-review', [RsoGraduationController::class, 'initialReview'])
            ->name('rso.graduation.initial');

        Route::post('/rso/graduation/{app}/return',      [RsoGraduationController::class, 'returnForCompliance'])
            ->name('rso.graduation.return');

        Route::post('/rso/graduation/{app}/final-review', [RsoGraduationController::class, 'finalReview'])
            ->name('rso.graduation.final');

        Route::post('/rso/graduation/{app}/decide',      [RsoGraduationController::class, 'decide']) // qualified/disqualified
            ->name('rso.graduation.decide');

        Route::post('/rso/graduation/{app}/endorse',     [RsoGraduationController::class, 'endorse'])
            ->name('rso.graduation.endorse');

        Route::post('/rso/graduation/{app}/notify',      [RsoGraduationController::class, 'notifyStudent'])
            ->name('rso.graduation.notify');

        Route::post('/rso/graduation/req/{req}/check',   [RsoGraduationController::class, 'checkRequirement'])
            ->whereNumber('req')
            ->name('rso.graduation.req.check');

        


        Route::get('/validate-grades', [ApplicationController::class, 'parseAndValidateGrades']);

    });
});

// routes/web.php
use App\Http\Controllers\Student\PsgcProxyController;

Route::prefix('psgc')->group(function () {
    Route::get('/regions', [PsgcProxyController::class, 'regions']);
    Route::get('/regions/{region}/provinces', [PsgcProxyController::class, 'provinces']);
    Route::get('/provinces/{province}/cities-municipalities', [PsgcProxyController::class, 'cities']);
    Route::get('/cities-municipalities/{city}/barangays', [PsgcProxyController::class, 'barangays']);
});

use App\Http\Controllers\PostalController;
Route::prefix('postal-ph')->group(function () {
    Route::get('/data', [PostalController::class, 'data']);   // you already have this
    Route::get('/zip',  [PostalController::class, 'zip']);    // <-- add this
});







// Programchair
    Route::get('/programchair/programchairsidebar', [ProgramchairController::class, 'index'])->name('programchair.programchairsidebar'); 
Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/programchair/deanshonorlist/{campusId?}', [DeansHonorListController::class, 'index'])->name('programchair.deanshonorlist');
    Route::get('/programchair/application/view/{id}', [DeansHonorListController::class, 'viewFile'])->name('programchair.application.view');
    Route::get('/programchair/programs/{collegeId}', [DeansHonorListController::class, 'getProgramsByCollege'])->name('programchair.programs');
    Route::get('/programchair/majors/{programId}', [DeansHonorListController::class, 'getMajorsByProgram'])->name('programchair.majors');
    Route::get('/programchair/students-by-program/{programId}', [DeansHonorListController::class, 'getStudentsByProgram'])->name('programchair.students.by.program');
    Route::post('/programchair/application/update-status', [DeansHonorListController::class, 'updateStatus'])->name('programchair.application.update-status');
    Route::post('/programchair/application/bulk-verify', [DeansHonorListController::class, 'bulkVerify'])->name('programchair.application.bulk-verify');
});
    Route::get('/programchair/dashboard', [DashboardProgramchairController::class, 'index'])->name('programchair.dashboard');
Route::middleware(['web'])->group(function () {
    Route::get('/programchair/post', [PostController::class, 'index'])->name('programchair.post.index'); // <--- view form
    Route::post('/programchair/post', [PostController::class, 'store'])->name('programchair.post');       // <--- form submission
});
Route::middleware(['web'])->group(function () {
    // Entry route – decides where to send the user
    Route::get('/programchair/curriculum', [CurriculumController::class, 'entry'])->name('programchair.curriculum');
    Route::post('/programchair/curriculum/insert', [CurriculumController::class, 'insert'])->name('curriculum.insert');
    Route::delete('/programchair/curriculum/{id}', [CurriculumController::class, 'destroy'])->name('curriculum.destroy');
    Route::get('/programchair/curriculumupload', [CurriculumCollegeController::class, 'showUploadForm'])->name('programchair.curriculumupload');
    Route::post('/curriculum/upload', [CurriculumCollegeController::class, 'upload'])->name('curriculum.upload');
    Route::get('/curriculum/view/{curriculum_id}', [CurriculumCollegeController::class, 'view'])->name('curriculum.view');
    Route::delete('/programchair/curriculum-ay/{id}', [CurriculumCollegeController::class, 'destroyAy'])->whereNumber('id')->name('curriculumay.destroy');
});

Route::middleware(['web'])->group(function () {
    // Entry route – decides where to send the user
    Route::get('/programchair/rank', [RankController::class, 'index'])->name('programchair.rank');
    Route::post('/programchair/rank/save', [RankController::class, 'save'])->name('programchair.rank.save');

});





// Dean
Route::middleware(['web', 'auth'])
    ->prefix('dean')
    ->name('dean.')
    ->group(function () {

        // Sidebar/home
        Route::get('/deansidebar', [DeanSidebarController::class, 'index'])
            ->name('deansidebar');

        // Honor list navigation/data
        Route::get('/honorlist/{campusId?}', [HonorListController::class, 'index'])
            ->name('honorlist');

        Route::get('/programs/{collegeId}', [HonorListController::class, 'getProgramsByCollege'])
            ->name('programs');

        Route::get('/students-by-program/{programId}', [HonorListController::class, 'getStudentsByProgram'])
            ->name('students.by.program');

        Route::get('/application/view/{id}', [HonorListController::class, 'viewFile'])
            ->name('application.view');

        // ---- Notifications / approvals (single + bulk) ----
        Route::post('/application/update-status', [ApplicationNotifController::class, 'updateStatus'])
            ->name('application.update-status');

        Route::post('/application/bulk-approve', [ApplicationNotifController::class, 'bulkApprove'])
            ->name('application.bulk-approve');
    });



// Dashboard routes
Route::get('/', function () {
    return view('login'); // Login view
})->name('login');

Route::get('/registrar/layout', function () {
    return view('registrar/layout'); // Admin or main dashboard
})->name('registrar/dashboard');

Route::get('/userimport', function () {
    return view('registrar.userimport');
})->name('user.import');

Route::get('/registrar/studentmanagement', function () {
    return view('registrar/studentmanagement');
})->name('registrar.students.upload');

Route::get('/admin/usermanagement', function () {
    return view('admin.usermanagement');
})->name('admin.usermanagement'); // ✅ Match name

Route::get('/import-admin', function () {
    return view('admin.adminimport'); // your import page
})->name('admin.adminimport');

Route::get('/admin/adminlayout', function () {
    return view('admin/adminlayout');
})->name('admin/adminlayout');

Route::get('/student.applydeanslist', function () {
    return view('student.applydeanslist');
})->name('student.applydeanslist');

Route::get('/student.studentprofile', function () {
    return view('student.studentprofile');
})->name('student.studentprofile');

Route::get('/registrar/userprofile', function () {
    return view('registrar.userprofile');
})->name('registrar.userprofile');

Route::get('/admin/adminprofile', function () {
    return view('admin.adminprofile');
})->name('admin.adminprofile');


Route::get('/email-status', function () {
    return response()->json(['sent' => session('email_sent', false)]);
});

Route::get('/email-time', function () {
    return response()->json(['seconds' => session('email_time', 3)]); // fallback 3 sec
});

Route::get('/reset-email-status', function () {
    session()->forget(['email_sent', 'email_time']);
    return response()->json(['reset' => true]);
});




Route::get('/admin/sidebar', function () {
    return view('admin.adminsidebar');
})->name('admin.sidebar');




// Extra route files (if needed)
require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
