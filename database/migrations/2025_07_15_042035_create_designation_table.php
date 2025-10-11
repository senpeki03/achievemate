<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('designation', function (Blueprint $table) {
            $table->id('Designation_id');
            $table->string('Designation_name');
            $table->string('Access'); // or $table->text() if needed
        });
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('designation');
    }
};
