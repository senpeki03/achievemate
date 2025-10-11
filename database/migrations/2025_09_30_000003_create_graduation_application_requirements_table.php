<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('graduation_application_requirements', function (Blueprint $t) {
            $t->id();
            $t->foreignId('application_id')->constrained('graduation_applications')->cascadeOnDelete();
            $t->foreignId('requirement_type_id')->constrained('graduation_requirement_types');
            $t->string('file_path')->nullable(); // storage/app/public/...
            $t->boolean('is_submitted')->default(false);
            $t->boolean('is_approved')->default(false);
            $t->text('checker_note')->nullable();
            $t->timestamp('checked_at')->nullable();
            $t->foreignId('checked_by')->nullable()->constrained('users');
            $t->timestamps();

            $t->unique(['application_id','requirement_type_id']);
        });
    }
    public function down(): void {
        Schema::dropIfExists('graduation_application_requirements');
    }
};
