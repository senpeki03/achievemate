<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('graduation_status_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('application_id')->constrained('graduation_applications')->cascadeOnDelete();
            $t->foreignId('actor_id')->nullable()->constrained('users');
            $t->string('from_status')->nullable();
            $t->string('to_status');
            $t->text('note')->nullable();
            $t->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('graduation_status_logs');
    }
};
