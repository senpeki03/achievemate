<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_student_assignments', function (Blueprint $table) {
            $table->increments('Assignment_id');   // int unsigned auto inc

            // ✅ match types with referenced tables (INT, not BIGINT)
            $table->unsignedInteger('Event_id');
            $table->unsignedInteger('Student_id');

            $table->string('status')->default('Pending'); 
            // Pending | Accepted | Refused | etc.

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_student_assignments');
    }
};
