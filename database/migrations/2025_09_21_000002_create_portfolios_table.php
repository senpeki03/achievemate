<?php

// database/migrations/2025_09_21_000002_create_portfolios_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('portfolios', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedInteger('Student_id');
            $t->string('type', 100);                 // e.g., DeanLister
            $t->string('title', 255);
            $t->text('description')->nullable();
            $t->string('badge_path', 500)->nullable();
            $t->string('certificate_path', 500)->nullable();
            $t->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('portfolios'); }
};


