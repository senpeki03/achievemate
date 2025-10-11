<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('college', function (Blueprint $table) {
            $table->id('College_id');
            $table->unsignedBigInteger('Campus_id');
            $table->string('Abbreviation');
            $table->string('College_name');
            $table->timestamp('Created_add')->useCurrent();

            // Optional: Add foreign key if you have a campuses table
            // $table->foreign('Campus_id')->references('Campus_id')->on('campuses')->onDelete('cascade');
        });
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('college');
    }
};
