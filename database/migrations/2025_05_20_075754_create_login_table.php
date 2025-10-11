<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('login', function (Blueprint $table) {
            $table->id('Login_id'); // ✅ Primary key with auto-increment
            $table->unsignedBigInteger('user_id')->unique(); // Optional, if you link to user_manage
            $table->string('username');
            $table->string('password');
            $table->string('usertype');
            $table->timestamps(); // Optional
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('login');
    }
};
