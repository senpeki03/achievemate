<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('graduation_applications', function (Blueprint $t) {
            $t->id();
            $t->foreignId('student_id')->constrained('users');
            $t->foreignId('college_id')->nullable()->constrained('colleges');  // if you already have these
            $t->foreignId('program_id')->nullable()->constrained('programs');  // else keep nullable
            $t->string('application_no')->unique();
            $t->date('term_end')->nullable(); // optional, if you want to tag which term

            // Status mirrors the handbook flow
            $t->enum('status', [
                'draft',                // before student submits
                'submitted',            // 1. SUBMIT
                'under_initial_review', // 2.1 CONDUCT initial evaluation
                'for_compliance',       // 2.3 RETURN w/ deficiencies; 3. COMPLY
                'resubmitted',          // back from student
                'under_final_review',   // 3.1 REVIEW & validate
                'for_deliberation',     // 3.3 CONDUCT final evaluation
                'qualified',            // 4.1/4.2 DECIDE = qualified
                'disqualified',         // DECIDE = not qualified
                'endorsed',             // 4.3/4.4 ENDORSE
                'notified'              // 5. SEND notice
            ])->default('draft');

            // Stamps & meta
            $t->timestamp('submitted_at')->nullable();
            $t->timestamp('initial_reviewed_at')->nullable();
            $t->timestamp('returned_for_compliance_at')->nullable();
            $t->timestamp('resubmitted_at')->nullable();
            $t->timestamp('final_reviewed_at')->nullable();
            $t->timestamp('deliberated_at')->nullable();
            $t->timestamp('decided_at')->nullable();
            $t->timestamp('endorsed_at')->nullable();
            $t->timestamp('notified_at')->nullable();

            $t->foreignId('initial_reviewer_id')->nullable()->constrained('users');
            $t->foreignId('final_reviewer_id')->nullable()->constrained('users');
            $t->foreignId('decider_id')->nullable()->constrained('users');
            $t->foreignId('endorser_id')->nullable()->constrained('users');

            $t->text('remarks')->nullable(); // running remarks
            $t->json('deficiency_notes')->nullable(); // structured list produced during returns

            $t->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('graduation_applications');
    }
};
