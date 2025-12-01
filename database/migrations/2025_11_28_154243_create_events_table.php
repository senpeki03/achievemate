<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->increments('Event_id');

            // No FK, simple int
            $table->integer('UserDesignation_id')->nullable();

            // 🔹 NEW: event type (FK to event_types.EventType_id)
            $table->unsignedInteger('EventType_id')->nullable();

            $table->string('title');
            $table->text('description')->nullable();
            $table->dateTime('start_at');
            $table->dateTime('end_at');

            // Number of students required for this event (your original column)
            $table->unsignedInteger('number_of_students')->default(0);

            // NEW: number of students already assigned
            $table->unsignedInteger('assigned_count')->default(0);

            $table->string('status')->default('Draft');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
