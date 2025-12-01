<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    /*
     * 1. Base lookup tables (no foreign keys)
     */

    Schema::create('academic_years', function (Blueprint $table) {
      $table->integer('academic_year_id')->autoIncrement();
      $table->string('label', 9);
      $table->date('start_date')->nullable();
      $table->date('end_date')->nullable();
      $table->boolean('is_current')->default(0);
    });

    Schema::create('campus', function (Blueprint $table) {
      $table->integer('Campus_id')->autoIncrement();
      $table->string('Campus_name', 250);
      $table->string('Location', 250);
      $table->date('Created_at');
    });

    Schema::create('designation', function (Blueprint $table) {
      $table->integer('Designation_id')->autoIncrement();
      $table->string('Designation_name', 250);
      $table->string('Access', 250);
    });

    Schema::create('login', function (Blueprint $table) {
      $table->integer('Login_id')->autoIncrement();
      $table->string('username', 250);
      $table->string('password', 250);
      $table->string('usertype', 250);
    });

    Schema::create('user_manage', function (Blueprint $table) {
      $table->integer('User_id')->autoIncrement();
      $table->string('Title', 250);
      $table->string('First_name', 250);
      $table->string('Middle_name', 250);
      $table->string('Last_name', 250);
      $table->string('Email', 250);
    });

    /*
     * 2. College / Program / Major / Curriculum hierarchy
     */

    Schema::create('college', function (Blueprint $table) {
      $table->integer('College_id')->autoIncrement();
      $table->integer('Campus_id');
      $table->string('Abbreviation', 250);
      $table->string('College_name', 250);
      $table->binary('Logo'); // mediumblob
      $table->date('Created_at');

      $table->index('Campus_id');
      $table->foreign('Campus_id')
        ->references('Campus_id')->on('campus')
        ->onDelete('cascade')
        ->onUpdate('cascade');
    });

    Schema::create('program', function (Blueprint $table) {
      $table->integer('Program_id')->autoIncrement();
      $table->integer('Campus_id');
      $table->integer('College_id');
      $table->string('Abbreviation', 250);
      $table->string('Program_name', 250);
      $table->date('Created_at');

      $table->index('Campus_id');
      $table->index('College_id');

      $table->foreign('College_id')
        ->references('College_id')->on('college')
        ->onDelete('cascade')
        ->onUpdate('cascade');

      $table->foreign('Campus_id')
        ->references('Campus_id')->on('campus')
        ->onDelete('cascade')
        ->onUpdate('cascade');
    });

    Schema::create('major', function (Blueprint $table) {
      $table->integer('Major_id')->autoIncrement();
      $table->integer('Campus_id');
      $table->integer('College_id');
      $table->integer('Program_id');
      $table->string('Abbreviation', 250);
      $table->string('Major_name', 250);
      $table->date('Created_at');

      $table->index('Campus_id');
      $table->index('College_id');
      $table->index('Program_id');

      $table->foreign('Campus_id')
        ->references('Campus_id')->on('campus')
        ->onDelete('cascade')
        ->onUpdate('cascade');

      $table->foreign('College_id')
        ->references('College_id')->on('college')
        ->onDelete('cascade')
        ->onUpdate('cascade');

      $table->foreign('Program_id')
        ->references('Program_id')->on('program')
        ->onDelete('cascade')
        ->onUpdate('cascade');
    });

    Schema::create('curriculum_ay', function (Blueprint $table) {
      $table->integer('CurriculumAY_id')->autoIncrement();
      $table->integer('Campus_id');
      $table->integer('College_id');
      $table->integer('Program_id')->nullable();
      $table->integer('Major_id')->nullable();

      $table->index('Campus_id');
      $table->index('College_id');
      $table->index('Program_id');
      $table->index('Major_id');

      $table->foreign('Campus_id')
        ->references('Campus_id')->on('campus')
        ->onDelete('cascade')
        ->onUpdate('cascade');

      $table->foreign('College_id')
        ->references('College_id')->on('college')
        ->onDelete('cascade')
        ->onUpdate('cascade');

      $table->foreign('Program_id')
        ->references('Program_id')->on('program')
        ->onDelete('cascade')
        ->onUpdate('cascade');

      $table->foreign('Major_id')
        ->references('Major_id')->on('major')
        ->onDelete('cascade')
        ->onUpdate('cascade');
    });

    Schema::create('curriculum', function (Blueprint $table) {
      $table->integer('curriculum_id')->autoIncrement();
      $table->integer('CurriculumAY_id')->nullable();
      $table->string('Curriculum_name', 225);
      $table->binary('File_data'); // longblob
      $table->string('Academic_year', 250);

      $table->index('CurriculumAY_id');
      $table->foreign('CurriculumAY_id')
        ->references('CurriculumAY_id')->on('curriculum_ay')
        ->onDelete('cascade')
        ->onUpdate('cascade');
    });

    Schema::create('curriculum_subjects', function (Blueprint $table) {
      $table->integer('subject_id')->autoIncrement();
      $table->integer('curriculum_id')->nullable();
      $table->string('Code', 255)->nullable();
      $table->string('Course_Title', 255)->nullable();
      $table->integer('units')->nullable();
      $table->integer('lec')->nullable();
      $table->integer('lab')->nullable();
      $table->string('prerequisite', 255)->nullable();
      $table->integer('total_units');
      $table->integer('total_lec');
      $table->integer('total_lab');
      $table->string('year_level', 250)->nullable();
      $table->string('semester', 250)->nullable();
      $table->string('track', 250)->nullable();

      $table->index('curriculum_id');
      $table->foreign('curriculum_id')
        ->references('curriculum_id')->on('curriculum')
        ->onDelete('cascade')
        ->onUpdate('cascade');
    });

    /*
     * 3. Student core info
     */

    Schema::create('student_manage', function (Blueprint $table) {
      $table->integer('Student_id')->autoIncrement();
      $table->integer('Login_id');
      $table->integer('curriculum_id');
      $table->string('SRCODE', 250);
      $table->string('First_name', 250);
      $table->string('Middle_name', 250);
      $table->string('Last_name', 250);
      $table->string('Contact', 250);
      $table->string('Year', 250);
      $table->string('Email', 250);
      $table->string('Academic_year', 250);

      $table->index('Login_id');
      $table->index('curriculum_id');

      $table->foreign('Login_id')
        ->references('Login_id')->on('login')
        ->onDelete('cascade')
        ->onUpdate('cascade');

      $table->foreign('curriculum_id')
        ->references('curriculum_id')->on('curriculum')
        ->onDelete('cascade')
        ->onUpdate('cascade');
    });

    /*
     * 4. Graduation forms, requirements, latin honors
     */

    Schema::create('graduation_form', function (Blueprint $table) {
      $table->integer('GraduationForm_id')->autoIncrement();
      $table->integer('Student_id');
      $table->date('Birthdate');
      $table->string('PlaceofBirth', 250);
      $table->string('HomeAddress', 250);
      $table->integer('ZIP_Code');
      $table->string('Sec_Grad', 250);
      $table->string('Sec_Grad_Year', 250);
      $table->string('Elem_Grad', 250);
      $table->string('Elem_Grad_Year', 250);
      $table->string('Scholarship_grant', 250);
      $table->string('Guardian_1', 250);
      $table->string('Guardian_1_Contact', 250);
      $table->string('Guardian_2', 250);
      $table->string('Guardian_2_Contact', 250);

      $table->index('Student_id');
      $table->foreign('Student_id')
        ->references('Student_id')->on('student_manage')
        ->onDelete('cascade')
        ->onUpdate('cascade');
    });

    Schema::create('graduation_requirements', function (Blueprint $table) {
      $table->integer('GraduationReq_id')->autoIncrement();
      $table->integer('GraduationForm_id');
      $table->string('Approval_Sheet', 255)->nullable();
      $table->string('Certificate_Library', 255)->nullable();
      $table->string('Barangay_Clearance', 255)->nullable();
      $table->string('Birth_Certificate', 255)->nullable();
      $table->string('applicationform_grad', 255)->nullable();
      $table->string('reportofgrade_path', 255)->nullable();
      $table->string('remarks', 250);
      $table->string('status', 250);

      $table->index('GraduationForm_id');

      $table->foreign('GraduationForm_id', 'fk_gradreq_form')
        ->references('GraduationForm_id')->on('graduation_form')
        ->onDelete('cascade')
        ->onUpdate('cascade');
    });

    Schema::create('latin', function (Blueprint $table) {
      $table->integer('Latin_id')->autoIncrement();
      $table->integer('Student_id');
      $table->integer('GraduationForm_id');
      $table->string('Consent', 250);
      $table->boolean('Status')->default(0);

      $table->index('Student_id');
      $table->index('GraduationForm_id');

      $table->foreign('Student_id')
        ->references('Student_id')->on('student_manage')
        ->onDelete('cascade')
        ->onUpdate('cascade');

      $table->foreign('GraduationForm_id')
        ->references('GraduationForm_id')->on('graduation_form')
        ->onDelete('cascade')
        ->onUpdate('cascade');
    });

    Schema::create('graduation_applications', function (Blueprint $table) {
      $table->unsignedInteger('Graduation_id')->autoIncrement();
      $table->unsignedInteger('Student_id')->nullable();
      $table->unsignedInteger('College_id')->nullable();
      $table->unsignedInteger('Program_id')->nullable();
      $table->string('application_no', 100)->nullable();
      $table->date('term_end')->nullable();
      $table->enum('status', [
        'draft',
        'for_compliance',
        'submitted',
        'approved',
        'rejected',
        'cancelled'
      ])->default('draft');
      $table->dateTime('submitted_at')->nullable();
      $table->dateTime('initial_reviewed_at')->nullable();
      $table->dateTime('returned_for_compliance_at')->nullable();
      $table->dateTime('resubmitted_at')->nullable();
      $table->dateTime('final_reviewed_at')->nullable();
      $table->dateTime('deliberated_at')->nullable();
      $table->dateTime('decided_at')->nullable();
      $table->dateTime('endorsed_at')->nullable();
      $table->dateTime('notified_at')->nullable();
      $table->unsignedInteger('initial_reviewer_id')->nullable();
      $table->unsignedInteger('final_reviewer_id')->nullable();
      $table->unsignedInteger('decider_id')->nullable();
      $table->unsignedInteger('endorser_id')->nullable();
      $table->text('remarks')->nullable();
      $table->longText('deficiency_notes')->nullable();
      $table->timestamp('created_at')->useCurrent();
      $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

      $table->index(['Student_id', 'status'], 'idx_student_status');
      $table->index('status', 'idx_status');
      $table->index('application_no', 'idx_application_no');
    });

    /*
     * 5. Grades & curriculum relationships
     */

    Schema::create('grades', function (Blueprint $table) {
      $table->integer('Grades_id')->autoIncrement();
      $table->integer('Student_id');
      $table->binary('image'); // mediumblob
      $table->string('sem', 250);
      $table->string('academic_year', 250);

      $table->index('Student_id');
      $table->foreign('Student_id')
        ->references('Student_id')->on('student_manage')
        ->onDelete('cascade')
        ->onUpdate('cascade');
    });

    Schema::create('student_course', function (Blueprint $table) {
      $table->integer('StudentCourse_id')->autoIncrement();
      $table->integer('Campus_id');
      $table->integer('College_id');
      $table->integer('Program_id');
      $table->integer('Major_id');
      $table->integer('Student_id');
      $table->integer('curriculum_id');

      $table->index('Campus_id');
      $table->index('College_id');
      $table->index('Program_id');
      $table->index('Major_id');
      $table->index('Student_id');
      $table->index('curriculum_id');

      $table->foreign('Campus_id')
        ->references('Campus_id')->on('campus')
        ->onDelete('cascade')
        ->onUpdate('cascade');

      $table->foreign('College_id')
        ->references('College_id')->on('college')
        ->onDelete('cascade')
        ->onUpdate('cascade');

      $table->foreign('Program_id')
        ->references('Program_id')->on('program')
        ->onDelete('cascade')
        ->onUpdate('cascade');

      $table->foreign('Major_id')
        ->references('Major_id')->on('major')
        ->onDelete('cascade')
        ->onUpdate('cascade');

      $table->foreign('Student_id')
        ->references('Student_id')->on('student_manage')
        ->onDelete('cascade')
        ->onUpdate('cascade');

      $table->foreign('curriculum_id')
        ->references('curriculum_id')->on('curriculum')
        ->onDelete('cascade')
        ->onUpdate('cascade');
    });

    Schema::create('student_grades', function (Blueprint $table) {
      $table->integer('student_grade_id')->autoIncrement();
      $table->integer('Student_id');
      $table->string('course_code', 11);
      $table->integer('subject_id');
      $table->integer('academic_year_id');
      $table->string('semester', 10);
      $table->string('grade', 250);
      $table->string('section', 50);
      $table->string('instructor', 50);
      $table->string('remarks', 11);

      $table->index('Student_id');
      $table->index('subject_id');
      $table->index('academic_year_id');

      $table->foreign('Student_id')
        ->references('Student_id')->on('student_manage')
        ->onDelete('cascade')
        ->onUpdate('cascade');

      $table->foreign('subject_id')
        ->references('subject_id')->on('curriculum_subjects')
        ->onDelete('cascade')
        ->onUpdate('cascade');

      $table->foreign('academic_year_id')
        ->references('academic_year_id')->on('academic_years')
        ->onDelete('cascade')
        ->onUpdate('cascade');
    });

    /*
     * 6. Portfolios & student notifications
     */

    Schema::create('portfolios', function (Blueprint $table) {
      $table->unsignedBigInteger('Portfolio_id')->autoIncrement();
      $table->unsignedInteger('Student_id');
      $table->string('type', 100);
      $table->string('title', 255);
      $table->text('description')->nullable();
      $table->string('badge_path', 500)->nullable();
      $table->string('certificate_path', 500)->nullable();
      $table->timestamp('created_at')->nullable();
      $table->timestamp('updated_at')->nullable();

      $table->index('Student_id');
      $table->index('type');
    });

    Schema::create('student_notifications', function (Blueprint $table) {
      $table->unsignedBigInteger('StudentNotification_id')->autoIncrement();
      $table->unsignedInteger('Student_id');
      $table->string('type', 100);
      $table->string('title', 255);
      $table->text('message')->nullable();
      $table->longText('data')->nullable(); // JSON-like
      $table->string('claim_token', 100)->nullable();
      $table->boolean('claimable')->default(0);
      $table->timestamp('claimed_at')->nullable();
      $table->boolean('is_read')->default(0);
      $table->timestamp('created_at')->nullable();
      $table->timestamp('updated_at')->nullable();

      $table->unique('claim_token');
      $table->index('Student_id');
      $table->index('type');
    });

    /*
     * 7. Profile tables
     */

    Schema::create('profile', function (Blueprint $table) {
      $table->integer('Profile_id')->autoIncrement();
      $table->integer('Student_id');
      $table->binary('Profile'); // mediumblob

      $table->index('Student_id');
      $table->foreign('Student_id')
        ->references('Student_id')->on('student_manage')
        ->onDelete('cascade')
        ->onUpdate('cascade');
    });

    Schema::create('userprofile', function (Blueprint $table) {
      $table->integer('Userprofile_id')->autoIncrement();
      $table->integer('User_id');
      $table->binary('Profile'); // mediumblob

      $table->index('User_id');
      $table->foreign('User_id')
        ->references('User_id')->on('user_manage')
        ->onDelete('cascade')
        ->onUpdate('cascade');
    });

    /*
     * 8. User designation / posts / applications
     */

    Schema::create('user_designation', function (Blueprint $table) {
      $table->integer('UserDesignation_id')->autoIncrement();
      $table->integer('Campus_id')->nullable();
      $table->integer('College_id')->nullable();
      $table->integer('Program_id')->nullable();
      $table->integer('Major_id')->nullable();
      $table->integer('Designation_id');
      $table->integer('User_id');
      $table->integer('Login_id')->nullable();

      $table->index('Designation_id');
      $table->index('User_id');
      $table->index('Campus_id');
      $table->index('College_id');
      $table->index('Program_id');
      $table->index('Major_id');
      $table->index('Login_id');

      $table->foreign('Designation_id')
        ->references('Designation_id')->on('designation')
        ->onDelete('cascade')
        ->onUpdate('cascade');

      $table->foreign('User_id')
        ->references('User_id')->on('user_manage')
        ->onDelete('cascade')
        ->onUpdate('cascade');

      $table->foreign('Login_id')
        ->references('Login_id')->on('login')
        ->onDelete('set null')
        ->onUpdate('cascade');

      $table->foreign('Campus_id')
        ->references('Campus_id')->on('campus')
        ->onDelete('set null')
        ->onUpdate('cascade');

      $table->foreign('College_id')
        ->references('College_id')->on('college')
        ->onDelete('set null')
        ->onUpdate('cascade');

      $table->foreign('Program_id')
        ->references('Program_id')->on('program')
        ->onDelete('set null')
        ->onUpdate('cascade');

      $table->foreign('Major_id')
        ->references('Major_id')->on('major')
        ->onDelete('set null')
        ->onUpdate('cascade');
    });

    Schema::create('post', function (Blueprint $table) {
      $table->integer('Post_id')->autoIncrement();
      $table->integer('UserDesignation_id');
      $table->string('Title', 255);
      $table->longText('Announcement');
      $table->string('Academic_year', 250);
      $table->string('Semester', 250);
      $table->date('Start_date');
      $table->date('End_date');
      $table->binary('image')->nullable(); // mediumblob

      $table->index('UserDesignation_id');
      $table->foreign('UserDesignation_id')
        ->references('UserDesignation_id')->on('user_designation')
        ->onDelete('cascade')
        ->onUpdate('cascade');
    });

    Schema::create('application', function (Blueprint $table) {
      $table->integer('Application_id')->autoIncrement();
      $table->integer('Student_id');
      $table->integer('Post_id');
      $table->string('Type', 250);
      $table->string('File_name', 250);
      $table->binary('File_data'); // longblob
      $table->float('GWA');
      $table->string('Rank', 250);
      $table->string('Status', 250);

      $table->index('Student_id');
      $table->index('Post_id');

      $table->foreign('Student_id', 'fk_application_student')
        ->references('Student_id')->on('student_manage')
        ->onDelete('cascade')
        ->onUpdate('cascade');

      $table->foreign('Post_id', 'fk_post')
        ->references('Post_id')->on('post')
        ->onDelete('cascade')
        ->onUpdate('cascade');
    });

    Schema::create('application_recipient', function (Blueprint $table) {
      $table->integer('Application_recipient_id')->autoIncrement();
      $table->integer('Application_id');
      $table->integer('User_id');
      $table->integer('is_read');

      $table->index('Application_id');
      $table->index('User_id');

      $table->foreign('Application_id')
        ->references('Application_id')->on('application')
        ->onDelete('cascade')
        ->onUpdate('cascade');

      $table->foreign('User_id')
        ->references('User_id')->on('user_manage')
        ->onDelete('cascade')
        ->onUpdate('cascade');
    });

    Schema::create('post_recipient', function (Blueprint $table) {
      $table->integer('PostRecipient_id')->nullable(); // as in dump (no PK)
      $table->integer('Post_id');
      $table->integer('Student_id');
      $table->boolean('is_read');

      $table->index('Post_id');
      $table->index('Student_id');

      $table->foreign('Post_id')
        ->references('Post_id')->on('post')
        ->onDelete('cascade')
        ->onUpdate('cascade');

      $table->foreign('Student_id')
        ->references('Student_id')->on('student_manage')
        ->onDelete('cascade')
        ->onUpdate('cascade');
    });

    /*
     * 9. Approved, evaluation, rank
     */

    Schema::create('approved', function (Blueprint $table) {
      $table->integer('Approved_id')->autoIncrement();
      $table->integer('Student_id');
      $table->integer('User_id');
      $table->date('Date');
      $table->string('Academic_year', 250);

      $table->index('Student_id');
      $table->index('User_id');

      $table->foreign('Student_id')
        ->references('Student_id')->on('student_manage')
        ->onDelete('cascade')
        ->onUpdate('cascade');

      $table->foreign('User_id')
        ->references('User_id')->on('user_manage')
        ->onDelete('cascade')
        ->onUpdate('cascade');
    });

    Schema::create('evaluation', function (Blueprint $table) {
      $table->integer('Evaluation_id')->autoIncrement();
      $table->integer('Student_id');
      $table->integer('User_id');
      $table->date('Date');
      $table->string('Academic_year', 250);

      $table->index('Student_id');
      $table->index('User_id');

      $table->foreign('Student_id')
        ->references('Student_id')->on('student_manage')
        ->onDelete('cascade')
        ->onUpdate('cascade');

      $table->foreign('User_id')
        ->references('User_id')->on('user_manage')
        ->onDelete('cascade')
        ->onUpdate('cascade');
    });

    Schema::create('rank', function (Blueprint $table) {
      $table->integer('Rank_id')->autoIncrement();
      $table->integer('User_id')->nullable();
      $table->decimal('min_gwa', 6, 4)->nullable();
      $table->decimal('max_gwa', 6, 4)->nullable();
      $table->boolean('is_rule')->default(0);
      $table->decimal('GWA', 6, 4)->nullable();
      $table->string('Rank', 250);

      $table->index(['is_rule', 'min_gwa', 'max_gwa'], 'idx_rank_rules');
      $table->index(['is_rule', 'User_id'], 'idx_rank_results');
      $table->index('User_id', 'fk_rank_user');

      $table->foreign('User_id')
        ->references('User_id')->on('user_manage')
        ->onDelete('set null')
        ->onUpdate('cascade');
    });

    /*
     * 10. System logs
     */

    Schema::create('system_logs', function (Blueprint $table) {
      $table->integer('systemId');
      $table->integer('userId');
      $table->string('action', 250);
      $table->string('ip_address', 250);
      $table->time('created_at');
      // no primary key in original dump
    });
  }

  public function down(): void
  {
    // Drop in reverse order of creation to avoid FK issues

    Schema::dropIfExists('system_logs');
    Schema::dropIfExists('rank');
    Schema::dropIfExists('evaluation');
    Schema::dropIfExists('approved');
    Schema::dropIfExists('post_recipient');
    Schema::dropIfExists('application_recipient');
    Schema::dropIfExists('application');
    Schema::dropIfExists('post');
    Schema::dropIfExists('user_designation');
    Schema::dropIfExists('userprofile');
    Schema::dropIfExists('profile');
    Schema::dropIfExists('student_notifications');
    Schema::dropIfExists('portfolios');
    Schema::dropIfExists('student_grades');
    Schema::dropIfExists('student_course');
    Schema::dropIfExists('grades');
    Schema::dropIfExists('graduation_applications');
    Schema::dropIfExists('latin');
    Schema::dropIfExists('graduation_requirements');
    Schema::dropIfExists('graduation_form');
    Schema::dropIfExists('student_manage');
    Schema::dropIfExists('curriculum_subjects');
    Schema::dropIfExists('curriculum');
    Schema::dropIfExists('curriculum_ay');
    Schema::dropIfExists('major');
    Schema::dropIfExists('program');
    Schema::dropIfExists('college');
    Schema::dropIfExists('user_manage');
    Schema::dropIfExists('login');
    Schema::dropIfExists('designation');
    Schema::dropIfExists('campus');
    Schema::dropIfExists('academic_years');
  }
};
