<?php

use App\Http\Controllers\Programchair\EventController;
use App\Http\Controllers\Student\StudentEventController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use App\Http\Middleware\EncryptCookies;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Routing\Middleware\SubstituteBindings;
/* ===============================
 * CONTROLLER USES
 * =============================== */
// ADMIN
use App\Http\Controllers\Admin\UserListController;
use App\Http\Controllers\Admin\UserImportController;
use App\Http\Controllers\Admin\SystemLogsController;
use App\Http\Controllers\Admin\CampusController;
use App\Http\Controllers\Admin\CollegeController;
use App\Http\Controllers\Admin\ProgramController;
use App\Http\Controllers\Admin\MajorController;
use App\Http\Controllers\Admin\UserManageController;
use App\Http\Controllers\Admin\DesignationController;
use App\Http\Controllers\Admin\UserDesignationController;

// REGISTRAR
use App\Http\Controllers\Registrar\Controller;
use App\Http\Controllers\Registrar\DashboardController;
use App\Http\Controllers\Registrar\DashboardRegistrarController;
use App\Http\Controllers\Registrar\StudentUploadController;
use App\Http\Controllers\Registrar\StudentController;
use App\Http\Controllers\Registrar\StudentListController;
use App\Http\Controllers\Registrar\GraduationListController;




// STUDENT
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
use App\Http\Controllers\Student\StudentProfileController;
use App\Http\Controllers\Student\GraduationRequirementController;
use App\Http\Controllers\Student\GraduationStatusController;
use App\Http\Controllers\Student\LatinHonorsController;
use App\Http\Controllers\Student\LatinController;
use App\Http\Controllers\Student\CogParseController;
use App\Http\Controllers\Student\StudentGradeController;
use App\Http\Controllers\Student\ViewGradeController;


// PROGRAM CHAIRPERSON
use App\Http\Controllers\Programchair\DeansHonorListController;
use App\Http\Controllers\Programchair\ProgramchairController;
use App\Http\Controllers\Programchair\PostController;
use App\Http\Controllers\Programchair\CurriculumController;
use App\Http\Controllers\Programchair\CurriculumCollegeController;
use App\Http\Controllers\Programchair\DashboardProgramchairController;
use App\Http\Controllers\Programchair\RankController;
use App\Http\Controllers\Programchair\DeansHonorListReportController;
use App\Http\Controllers\Programchair\ProgramchairProfileController;
use App\Http\Controllers\Programchair\GraduationController;
use App\Http\Controllers\Programchair\PostingController;
use App\Http\Controllers\Programchair\ProgramChairNotificationController;
use App\Http\Controllers\Programchair\GraduationReportController;

// DEAN
use App\Http\Controllers\Dean\DeanSidebarController;
use App\Http\Controllers\Dean\HonorListController;
use App\Http\Controllers\Dean\ApplicationNotifController;
use App\Http\Controllers\Dean\DeanDashboardController;
use App\Http\Controllers\Dean\DeansHonorListReportController as DeanReport;
use App\Http\Controllers\Dean\DeanProfileController;

// EXTRA (appeared mid-file originally — lifting to top to keep tidy)
use App\Http\Controllers\Dean\DeanApplicationController;
use App\Http\Controllers\Student\AwardClaimController;
use App\Http\Controllers\Student\GraduationApplicationController;
use App\Http\Controllers\Student\GraduationApplicationController as RsoGraduationController;
use App\Http\Controllers\Student\PsgcProxyController;
use App\Http\Controllers\Student\PostalController;

/* ===============================
 * AUTH
 * =============================== */
Route::post('/signup', [AuthController::class, 'register'])->name('register.submit');
Route::post('/signin', [AuthController::class, 'login'])->name('login.submit');
Route::get('/logout', [AuthController::class, 'logout'])->name('logout');

/* ===============================
 * ADMIN (standalone + prefixed)
 * =============================== */
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
Route::get('/admin/students/{department}/{year}/{track}', [UserListController::class, 'showStudentsByTrack'])->name('admin.students.by.track');

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

/* ===============================
 * REGISTRAR
 * =============================== */

Route::get('/registrar/dashboard', [DashboardRegistrarController::class, 'index'])->name('registrar.dashboard');

Route::middleware(['web'])->group(function () {
    Route::get('/registrar/student', [StudentController::class, 'index'])->name('registrar.student');
    Route::get('/registrar/studentlist', [StudentController::class, 'studentList'])->name('registrar.studentlist');
    Route::post('/registrar/student/assign-year', [StudentController::class, 'assignYear'])->name('student.assign.year'); 
    Route::get('/registrar/curricula/options',[StudentController::class, 'curriculumOptions'])->name('registrar.curriculum.options');
    Route::delete('/registrar/studentlist/delete/{id}', [StudentListController::class, 'destroy'])->name('registrar.studentlist.delete');
});

Route::middleware(['web', 'auth'])
    ->prefix('registrar')
    ->name('registrar.')
    ->group(function () {
    Route::get('/graduationlist', [GraduationListController::class, 'index'])->name('graduationlist');
    Route::get('/graduationlist/students', [GraduationListController::class, 'students'])->name('graduationlist.students');
    Route::delete('/graduationlist/{id}', [GraduationListController::class, 'destroy'])->name('graduationlist.destroy');
    Route::post('/graduationlist/evaluate', [GraduationListController::class, 'evaluate'])->name('graduationlist.evaluate');

});

Route::middleware(['web','auth'])->group(function () {
    Route::get('/registrar/studentupload', [StudentUploadController::class, 'showUploadForm'])->name('registrar.studentupload');
    Route::post('/registrar/student/upload-csv', [StudentUploadController::class, 'uploadCSV'])->name('student.upload.csv');

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

/* ===============================
 * STUDENT (prefixed)
 * =============================== */
Route::middleware(['web', 'auth'])
    ->prefix('student')
    ->name('student.')
    ->group(function () {
        Route::get('/dashboard', [DashboardStudentController::class, 'index'])->name('dashboard');
        Route::controller(NotificationController::class)->group(function () {
        Route::get('/notifications', 'showNotifications')->name('notifications');
        Route::post('/notifications/markAsRead', 'markAsRead')->name('notifications.markAsRead');
        });
        Route::get('/application', [ApplicationController::class, 'showApplication'])->name('application');
        Route::post('/application/submit', [ApplicationController::class, 'submitApplication'])->name('application.submit');
        Route::post('/application', [ApplicationController::class, 'store'])->name('application.submit');
        Route::post('/pdf/generate', [ApplicationController::class, 'regenerateDeanListForm'])->name('pdf.generate'); // intentionally 410
        Route::post('/pdf/generate-json', [ApplicationController::class, 'generateDeanListFormFromJson'])->name('pdf.generate.json');        
        Route::post('/upload-attachments', [ApplicationController::class, 'uploadAttachments'])->name('upload.attachments');
        Route::post('/attachments', [AttachmentsController::class, 'store'])->name('attachments.store');
        Route::post('/save-cor-output', [ApplicationController::class, 'saveCorOutput'])->name('save-cor-output');
        Route::post('/save-cog-output', [ApplicationController::class, 'saveCogOutput'])->name('save-cog-output');
        Route::get('/cog/output', [ApplicationController::class, 'getCogOutputFile'])->name('cog.output');
        Route::post('/cog/debug', function (\Illuminate\Http\Request $r) {
            \Illuminate\Support\Facades\Storage::disk('local')
            ->append('cog/debug_log.txt', ($r->input('text') ?? '')."\n---\n");
            return response()->json(['ok' => true]);
        })->name('cog.debug');
        Route::get('/cog/ocr-output', [ApplicationController::class, 'getCogOcrOutputFile'])->name('cog.ocr-output');
        Route::get('/cor/output', [ApplicationController::class, 'getCorOutputFile'])->name('cor.output');
        Route::get('/cor/output.txt', [ApplicationController::class, 'getCorOutputFile'])->name('cor.output.txt');
        Route::get('/cog/output.txt', [ApplicationController::class, 'getCogOutputFile'])->name('cog.output.txt');
        Route::post('/validate-grades', [ApplicationController::class, 'validateGrades'])->name('validate-grades');
        Route::get('/cog/debug-status', [ApplicationController::class, 'cogDebugStatus'])->name('cog.debugStatus');
        Route::post('/curriculum/subjects', [StudentCurriculumController::class, 'subjects'])->name('curriculum.subjects');
        Route::post('/js-error', [ApplicationController::class, 'logJsError'])->name('log-js-error');
        Route::get('/cog/parse-qr', [ApplicationController::class, 'getParseQrGrades'])->name('cog.qrGradesOnly');
        Route::get('/cog/parse-ocr', [ApplicationController::class, 'getParseOcrGrades'])->name('cog.ocrGradesOnly');
        Route::get('/applicationstatus', [ApplicationStatusController::class, 'index'])->name('application.status');
        Route::delete('/application/{id}', [ApplicationStatusController::class, 'destroy'])->name('application.destroy');
        Route::get('/check-cor-cog-codes', [ApplicationController::class, 'checkCorCogCodes'])->name('checkCorCogCodes');
        Route::get('/cog/academic-info', [ApplicationController::class, 'getCogAcademicInfo'])->name('cog.academic-info');
        Route::post('/validate-application-period', [ApplicationController::class, 'validateApplicationPeriod'])->name('validate.application-period');
        Route::post('/cor/upload', [PdfUploadController::class, 'upload'])->name('cor.upload');
        Route::post('/cog/upload', [CogUploadController::class, 'upload'])->name('cog.upload');
        Route::get('/portfolio', [PortfolioController::class, 'index'])->name('portfolio');
        Route::get('/cert/deans/{application}', [CertificateController::class, 'preview'])->name('cert.deans.preview');
        Route::get('/cert/deans/{application}/download', [CertificateController::class, 'download'])->name('cert.deans.download');
        Route::get('/graduation-form',  [GraduationFormController::class, 'show'])->name('graduation.show');
        Route::post('/graduation-form', [GraduationFormController::class, 'store'])->name('graduation.store');
        Route::post('/cor-upload', [GraduationFormController::class, 'uploadCor'])->name('graduation.cor.upload');
        Route::post('/cog-upload', [GraduationFormController::class, 'uploadCog'])->name('graduation.cog.upload');
        Route::get('/cog/extracted', [GraduationFormController::class, 'cogExtractedTitles'])->name('cog.extracted');
        Route::get('/debug-cor-detection', [GraduationFormController::class, 'debugCorDetection']);
        Route::get('/curriculum/subjects', [GraduationFormController::class, 'curriculumSubjects'])->name('graduation.curriculum.subjects');
        Route::post('/graduation-form/save', [GraduationFormController::class,'saveFields'])->name('graduationform.save');
            Route::prefix('graduation')->group(function () {
            Route::get('/status', [GraduationStatusController::class, 'show'])->name('graduation.status');
            Route::get('/status/print', [GraduationStatusController::class, 'printStatus'])->name('graduation.status.print');
            Route::get('/documents/download/{documentType}', [GraduationStatusController::class, 'downloadDocuments'])->name('graduation.documents.download');
            Route::post('/requirements/save',[GraduationStatusController::class, 'saveRequirement'])->name('graduation.requirements.save');
            Route::delete('/requirements',[GraduationStatusController::class, 'destroyRequirement'])->name('graduation.requirements.destroy');

        });
        Route::post('/graduation-form/generate-pdf', [GraduationFormController::class,'generateGradPdf'])->name('graduationform.generate');
        Route::post('/graduation/requirements', [GraduationRequirementController::class, 'store'])->name('graduation.requirements.store');
        Route::post('/graduation/evaluate-cor', [GraduationFormController::class, 'evaluateGraduationFromCor'])->name('graduation.evaluate-cor');
        Route::get('/graduation/evaluation', [GraduationFormController::class, 'getGraduationEvaluation'])->name('graduation.evaluation');
        Route::post('/latin/store', [LatinController::class, 'store'])->name('latin.store');
        Route::post('/student/graduationform/generate-consent', [GraduationFormController::class, 'generateConsentPdf'])
            ->name('student.graduationform.generateConsent')
            ->middleware('auth'); 
        Route::post('/cog/validate-from-text', [ApplicationController::class, 'validateCogFromText'])->name('cog.validateFromText');
        Route::get('/parse-grades', [ApplicationController::class, 'parseAndSaveGrades']);
        Route::get('/compare-grades', [ApplicationController::class, 'compareGrades']);
        Route::get('/profile', [StudentProfileController::class, 'index'])->name('profile');
        Route::get('/profile/photo', [StudentProfileController::class, 'photo'])->name('profile.photo');
        Route::post('/profile/save', [StudentProfileController::class, 'save'])->name('profile.save');
        Route::get('/latin', [LatinHonorsController::class, 'index'])->name('latin');
        Route::post('/latin/generate', [LatinHonorsController::class, 'generate'])->name('latin.generate');
        Route::post('/latin-honors/consent-generate', [LatinHonorsController::class, 'generate'])->name('latin-honors.consent.generate');
        Route::get('/studentgrade', [StudentGradeController::class, 'index'])->name('studentgrade');
        Route::get('/studentgrade/preview/image', [StudentGradeController::class, 'getSavedPng'])->name('studentgrade.preview.image');
        Route::get('/grade', fn () => view('student.studentgrade'))->name('studentgrade.view');
        Route::post('/upload-cog-preview', [StudentGradeController::class, 'uploadPreview'])->name('upload.preview');
        Route::post('/qr/save',   [StudentGradeController::class, 'storeQrOutput'])->name('save-qr-output');
        Route::get ('/qr/output', [StudentGradeController::class, 'qrOutput'])->name('qr.output');
        Route::get('/cog/output-studentgrade', [StudentGradeController::class, 'cogOutput'])->name('cog.output.studentgrade');
        Route::post('/grades/save', [StudentGradeController::class, 'storeFromCog'])->name('grades.store');            
        Route::get('/debug-storage', [StudentGradeController::class, 'debugStorage']);
        Route::get('/upload-grade', [StudentGradeController::class, 'index'])->name('studentgrade');
        Route::prefix('grades')->name('grades.')->group(function () {
            Route::get('/',     [ViewGradeController::class, 'index'])->name('index');
            Route::get('/view', [ViewGradeController::class, 'index'])->name('view'); // UI page
            Route::get('/modal',      [ViewGradeController::class, 'getGradesModal'])->name('modal');
            Route::get('/copy-modal', [ViewGradeController::class, 'getCopyOfGradesModal'])->name('copy-modal');
            Route::get('/all-modal',  [ViewGradeController::class, 'getAllGradesModal'])->name('all-modal');
            Route::get('/upload',  [ViewGradeController::class, 'redirectToUpload'])->name('upload');
            Route::post('/upload', [ViewGradeController::class, 'uploadGrades'])->name('upload.store');
            Route::get('/all',  [ViewGradeController::class, 'showAllGrades'])->name('all');
            Route::get('/copy', [ViewGradeController::class, 'showCopyOfGrades'])->name('copy');
            Route::get('/debug', [ViewGradeController::class, 'debugGrades'])->name('debug');
            Route::get('/years', [ViewGradeController::class, 'getStudentAcademicYears'])->name('years');
            Route::post('/send-auto-verification-code', [ViewGradeController::class, 'sendAutoVerificationCode'])->name('send-auto-verification-code');
            Route::post('/resend-verification-code', [ViewGradeController::class, 'resendVerificationCode'])->name('resend-verification-code');
            Route::post('/verify-code', [ViewGradeController::class, 'verifyCode'])->name('verify-code');
            
});
        Route::post('/change-password', [StudentProfileController::class, 'changePassword'])->name('password.change');
    });

        Route::get('/student/grades/image/{studentId}', function($studentId) {
            try {
                $gradesRecord = \App\Models\Grades::where('Student_id', $studentId)->first();
                
                if (!$gradesRecord || empty($gradesRecord->image)) {
                    return response()->json(['error' => 'No image found for student'], 404);
                }

                return response($gradesRecord->image)
                    ->header('Content-Type', 'image/png')
                    ->header('Content-Length', strlen($gradesRecord->image))
                    ->header('Cache-Control', 'no-cache, no-store, must-revalidate');
                    
            } catch (\Exception $e) {
                return response()->json(['error' => $e->getMessage()], 500);
            }
        });

    Route::post('student/qr/resolve', [QrResolverController::class, 'resolve'])
        ->name('student.qr.resolve')
        ->withoutMiddleware([
            // remove the whole web group in one go
            'web',

            // and explicitly remove the framework classes in case your app adds them directly
            \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class,
            \Illuminate\Cookie\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ])
        ->middleware('throttle:20,1');



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


Route::get('/awards/claim/{token}', [\App\Http\Controllers\Student\AwardClaimController::class, 'claim'])
    ->middleware(['web','auth'])
    ->name('student.award.claim');

Route::get('/student/certificates/{applicationId}/png', [CertificateController::class, 'generatePng'])
    ->name('student.certificates.png');

Route::get('/phpinfo', function () { phpinfo(); }); // existed below too; keeping here as well

/* ===============================
 * GRADUATION (student + RSO)
 * =============================== */
Route::middleware(['auth'])->group(function () {

    // Student-facing Graduation module
    Route::get('/student/apply',                  [GraduationApplicationController::class, 'create'])->name('student.apply');
    Route::post('/student/graduation/apply',      [GraduationApplicationController::class, 'store'])->name('student.graduation.store');
    Route::get('/student/graduation/my-application', [GraduationApplicationController::class, 'show'])->name('student.graduation.application'); // different name
    Route::post('/student/graduation/submit',   [GraduationApplicationController::class, 'submit'])->name('student.graduation.submit');
    Route::post('/student/graduation/resubmit', [GraduationApplicationController::class, 'resubmit'])->name('student.graduation.resubmit');

    Route::post('/student/graduation/upload/{req}', [GraduationApplicationController::class, 'uploadRequirement'])->whereNumber('req')->name('student.graduation.upload');

    //StudentEventController added
    Route::get('student/event-invites', [StudentEventController::class, 'index'])->name('student.event.invite');
    Route::post('/event-invites/{invite}/status', [StudentEventController::class, 'updateStatus'])->name('student.event-update');

    // RSO / Program Chair – add your gate/role middleware (e.g. can:rso)
    Route::middleware('can:manage-graduation')->group(function () {
        Route::get('/rso/graduation',                    [RsoGraduationController::class, 'rsoIndex'])->name('rso.graduation.index');
        Route::get('/rso/graduation/{app}',              [RsoGraduationController::class, 'evaluate'])->name('rso.graduation.evaluate');
        Route::post('/rso/graduation/{app}/initial-review', [RsoGraduationController::class, 'initialReview'])->name('rso.graduation.initial');
        Route::post('/rso/graduation/{app}/return',      [RsoGraduationController::class, 'returnForCompliance'])->name('rso.graduation.return');
        Route::post('/rso/graduation/{app}/final-review', [RsoGraduationController::class, 'finalReview'])->name('rso.graduation.final');
        Route::post('/rso/graduation/{app}/decide',      [RsoGraduationController::class, 'decide'])->name('rso.graduation.decide'); // qualified/disqualified
        Route::post('/rso/graduation/{app}/endorse',     [RsoGraduationController::class, 'endorse'])->name('rso.graduation.endorse');
        Route::post('/rso/graduation/{app}/notify',      [RsoGraduationController::class, 'notifyStudent'])->name('rso.graduation.notify');
        Route::post('/rso/graduation/req/{req}/check',   [RsoGraduationController::class, 'checkRequirement'])->whereNumber('req')->name('rso.graduation.req.check');
    });
});

/* ===============================
 * PSGC / POSTAL
 * =============================== */
Route::prefix('psgc')->group(function () {
    Route::get('/regions', [PsgcProxyController::class, 'regions']);
    Route::get('/regions/{region}/provinces', [PsgcProxyController::class, 'provinces']);
    Route::get('/provinces/{province}/cities-municipalities', [PsgcProxyController::class, 'cities']);
    Route::get('/cities-municipalities/{city}/barangays', [PsgcProxyController::class, 'barangays']);
});

Route::prefix('postal-ph')->group(function () {
    Route::get('/zip',  [PostalController::class, 'zip'])->name('postal.zip');
    Route::get('/data', [PostalController::class, 'data'])->name('postal.data');
});

/* ===============================
 * PROGRAM CHAIR
 * =============================== */
Route::get('/programchair/programchairsidebar', [ProgramchairController::class, 'index'])->name('programchair.programchairsidebar');

Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/programchair/deanshonorlist/{campusId?}', [DeansHonorListController::class, 'index'])->name('programchair.deanshonorlist');
    Route::get('/programchair/application/view/{id}', [DeansHonorListController::class, 'viewFile'])->name('programchair.application.view');
    Route::get('/programchair/programs/{collegeId}', [DeansHonorListController::class, 'getProgramsByCollege'])->name('programchair.programs');
    Route::get('/programchair/majors/{programId}', [DeansHonorListController::class, 'getMajorsByProgram'])->name('programchair.majors');
    Route::get('/programchair/students-by-program/{programId}', [DeansHonorListController::class, 'getStudentsByProgram'])->name('programchair.students.by.program');
    Route::post('/programchair/application/update-status', [DeansHonorListController::class, 'updateStatus'])->name('programchair.application.update-status');
    Route::post('/programchair/application/bulk-verify', [DeansHonorListController::class, 'bulkVerify'])->name('programchair.application.bulk-verify');
    Route::get('/programchair/dashboard', [DashboardProgramchairController::class, 'index'])->name('programchair.dashboard');
    Route::get('/programchair/graduation', [GraduationController::class, 'index'])->name('programchair.graduation');
    Route::get('/programchair/graduation/report', [GraduationReportController::class, 'generateReport'])->name('programchair.graduation.report');
    
    //Event added
    Route::prefix('programchair')->name('programchair.')->group(function () {
      Route::get('/events', [EventController::class, 'index'])->name('events');
      Route::post('/events', [EventController::class, 'store'])->name('events.store');
      Route::put('/events/{event}', [EventController::class, 'update'])->name('events.update');
      Route::delete('/events/{event}', [EventController::class, 'destroy'])->name('events.destroy');
      Route::post('/events/invite', [EventController::class, 'inviteStudent'])->name('event-invite');
      Route::delete('/events/{event}/invites/{invite}', [EventController::class, 'cancelInvite'])->name('event-invite-cancel');
      Route::post('/event-types', [EventController::class, 'storeEventType'])->name('event-types.store');
      Route::put('/event-types/{type}', [EventController::class, 'updateEventType'])->name('event-types.update');
      Route::delete('/event-types/{type}', [EventController::class, 'destroyEventType'])->name('event-types.destroy');
    });
});

    Route::middleware(['auth', 'usertype:Program Chairperson'])->group(function () {
        Route::get('/programchair/notifications', [ProgramChairNotificationController::class, 'index'])->name('programchair.notifications.index');
        Route::post('/programchair/notifications/mark-as-read', [ProgramChairNotificationController::class, 'markAsRead'])->name('programchair.notifications.markAsRead');
    });


Route::middleware(['auth'])->prefix('programchair')->name('programchair.')->group(function () {
    Route::get('/deans-honor-list/options/{program}',[DeansHonorListReportController::class, 'options'])->name('deans-honor-list.options');
    Route::get('/deans-honor-list/report/{program}',[DeansHonorListReportController::class, 'download'])->name('deans-honor-list.report');
    Route::get('/deans-honor-list/download/{programId}', [PostingController::class, 'download'])->name('deans-honor-list.download');
    });


Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/programchair/post', [PostController::class, 'index'])->name('programchair.post.index'); // <--- view form
    Route::post('/programchair/post', [PostController::class, 'store'])->name('programchair.post');       // <--- form submission
});

Route::middleware(['auth'])
    ->prefix('programchair')
    ->name('programchair.')
    ->group(function () {
        // Landing for curriculum module (now uses entry())
        Route::get('/curriculum', [CurriculumController::class, 'entry'])
            ->name('curriculum');

        // Create AY (moved inside the group so name is programchair.curriculum.insert)
        Route::post('/curriculum/insert', [CurriculumController::class, 'insert'])
            ->name('curriculum.insert');

        // Upload page + actions
        Route::get('/curriculumupload', [CurriculumCollegeController::class, 'showUploadForm'])
            ->name('curriculumupload');

        Route::post('/curriculum/upload', [CurriculumCollegeController::class, 'upload'])
            ->name('curriculum.upload');

        Route::get('/curriculum/view/{curriculum_id}', [CurriculumCollegeController::class, 'view'])
            ->name('curriculum.view');

        Route::delete('/curriculum/{curriculum_id}', [CurriculumCollegeController::class, 'destroy'])
            ->name('curriculum.destroy');

        Route::delete('/curriculum-ay/{id}', [CurriculumCollegeController::class, 'destroyAy'])
            ->name('curriculumay.destroy');
    });

// Keep this OUTSIDE the group because your blades call route('curriculum.insert') (no programchair. prefix)
Route::post('/curriculum/insert', function (Illuminate\Http\Request $request) {
    $validated = $request->validate([
        'campus_id'     => 'required|exists:campus,Campus_id',
        'college_id'    => 'required|exists:college,College_id',
        'program_id'    => 'required|exists:program,Program_id',
        'major_id'      => 'nullable|exists:major,Major_id',
        'academic_year' => 'required|string|max:20',
    ]);

    $record = \App\Models\CurriculumAy::create([
        'Campus_id'     => $validated['campus_id'],
        'College_id'    => $validated['college_id'],
        'Program_id'    => $validated['program_id'],
        'Major_id'      => $validated['major_id'] ?? null,
        'Academic_year' => $validated['academic_year'],
    ]);

    return response()->json([
        'success'  => true,
        'message'  => 'Curriculum AY created.',
        'redirect' => route('programchair.curriculumupload'),
        'record'   => $record,
    ]);
})->name('curriculum.insert');


Route::middleware(['web'])->group(function () {
    Route::get('/programchair/rank', [RankController::class, 'index'])->name('programchair.rank');
    Route::post('/programchair/rank/save', [RankController::class, 'save'])->name('programchair.rank.save');
});

    Route::get('/programchair/profile',        [ProgramchairProfileController::class, 'show'])->name('programchair.profile');
    Route::get('/programchair/profile/photo',  [ProgramchairProfileController::class, 'photo'])->name('programchair.profile.photo');
    Route::post('/programchair/profile/save',  [ProgramchairProfileController::class, 'save'])->name('programchair.profile.save');
Route::middleware(['web', 'auth'])
    ->prefix('dean')
    ->name('dean.')
    ->group(function () {

        Route::get('/deansidebar', [DeanSidebarController::class, 'index'])
            ->name('deansidebar');

        Route::get('/honorlist/{campusId?}', [HonorListController::class, 'index'])
            ->name('honorlist');

        Route::get('/programs/{collegeId}', [HonorListController::class, 'getProgramsByCollege'])
            ->name('programs');

        Route::get('/students-by-program/{programId}', [HonorListController::class, 'getStudentsByProgram'])
            ->name('students.by.program');

        Route::get('/application/view/{id}', [HonorListController::class, 'viewFile'])
            ->name('application.view');

        // ===== Dean – approve (single + bulk) =====
        Route::post('/application/update-status', [DeanApplicationController::class, 'updateStatus'])
            ->name('application.update-status');

        Route::post('/application/bulk-approve', [DeanApplicationController::class, 'bulkApprove'])
            ->name('application.bulk-approve');
    }); 
    Route::get('/dean/profile',        [DeanProfileController::class, 'show'])->name('dean.profile');
    Route::get('/dean/profile/photo',  [DeanProfileController::class, 'photo'])->name('dean.profile.photo');
    Route::post('/dean/profile/save',  [DeanProfileController::class, 'save'])->name('dean.profile.save');


Route::middleware(['web','auth','usertype:Dean'])
    ->prefix('dean')
    ->name('dean.')
    ->group(function () {
        Route::get('/deanshonorlist/report/{programId}', [DeanReport::class, 'download'])
            ->name('deanshonorlist.report');
    });


Route::get('/dean/dashboard', [DeanDashboardController::class, 'index'])->name('dean.dashboard');

/* The following inline PHP existed in your file; leaving it intact but disabling execution
   to prevent fatal errors during route loading. Nothing is deleted/replaced. */
if (false) {
    // BEFORE
    $downloadLink = Route::has('dean.certificate.download')
        ? route('dean.certificate.download', ['application' => $app->Application_id ?? $app->id])
        : url('/student/certificates');          // ← this 404s

    // AFTER — send them to the Awards/Notifications page
    $downloadLink = Route::has('student.notifications')
        ? route('student.notifications')
        : url('/student/notifications');
}

Route::get('/student/certificates/{application}/download', function ($application) {
    // TODO: implement actual download
    return redirect('/student/certificates'); // temporary safe fallback
})->name('dean.certificate.download');

/* ===============================
 * BASIC VIEWS / LANDING + UTILS
 * =============================== */
Route::get('/', function () { return view('login'); })->name('login');

Route::get('/registrar/layout', function () { return view('registrar/layout'); })->name('registrar/dashboard');
Route::get('/userimport', function () { return view('registrar.userimport'); })->name('user.import');
Route::get('/registrar/studentmanagement', function () { return view('registrar/studentmanagement'); })->name('registrar.students.upload');
Route::get('/admin/usermanagement', function () { return view('admin.usermanagement'); })->name('admin.usermanagement'); // ✅ Match name
Route::get('/import-admin', function () { return view('admin.adminimport'); })->name('admin.adminimport');
Route::get('/admin/adminlayout', function () { return view('admin/adminlayout'); })->name('admin/adminlayout');
Route::get('/student.applydeanslist', function () { return view('student.applydeanslist'); })->name('student.applydeanslist');
Route::get('/student.studentprofile', function () { return view('student.studentprofile'); })->name('student.studentprofile');
Route::get('/registrar/userprofile', function () { return view('registrar.userprofile'); })->name('registrar.userprofile');
Route::get('/admin/adminprofile', function () { return view('admin.adminprofile'); })->name('admin.adminprofile');

Route::get('/email-status', function () { return response()->json(['sent' => session('email_sent', false)]); });
Route::get('/email-time', function () { return response()->json(['seconds' => session('email_time', 3)]); }); // fallback 3 sec
Route::get('/reset-email-status', function () { session()->forget(['email_sent', 'email_time']); return response()->json(['reset' => true]); });

Route::get('/admin/sidebar', function () { return view('admin.adminsidebar'); })->name('admin.sidebar');

/* ===============================
 * EXTRA ROUTE FILES
 * =============================== */
require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
