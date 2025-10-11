<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('campus', function (Blueprint $table) {
            $table->id('Campus_id');
            $table->string('Campus_name');
            $table->string('Location');
            $table->timestamp('Created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campus');
    }
};

