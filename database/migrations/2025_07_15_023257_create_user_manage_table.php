<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('user_manage', function (Blueprint $table) {
            $table->increments('User_id');
            $table->string('Title', 250)->nullable();
            $table->string('First_name', 250);
            $table->string('Middle_name', 250)->nullable();
            $table->string('Last_name', 250);
            $table->string('Email', 250)->unique();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_manage');
    }
};
