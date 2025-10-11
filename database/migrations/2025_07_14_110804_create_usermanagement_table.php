<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('usermanagement', function (Blueprint $table) {
            $table->id(); // auto-increment ID
            $table->unsignedBigInteger('Login_id');
            $table->string('Srcode')->unique();
            $table->string('firstname');
            $table->string('middlename')->nullable();
            $table->string('lastname');
            $table->string('email')->unique();
            $table->timestamps(); // created_at and updated_at
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usermanagement');
    }
};

