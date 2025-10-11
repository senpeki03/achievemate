<?php

// database/migrations/2025_09_21_000001_create_student_notifications_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('student_notifications', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedInteger('Student_id');                // FK to students table
            $t->string('type', 100);                          // e.g., deans_lister_award
            $t->string('title', 255);
            $t->text('message')->nullable();
            $t->json('data')->nullable();                     // paths, application id, etc.
            $t->string('claim_token', 100)->nullable()->unique();
            $t->boolean('claimable')->default(false);
            $t->timestamp('claimed_at')->nullable();
            $t->boolean('is_read')->default(false);
            $t->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('student_notifications'); }
};
