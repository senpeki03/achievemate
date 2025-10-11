<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAddProgramTable extends Migration
{
    public function up()
    {
        Schema::create('program', function (Blueprint $table) {
            $table->id('Program_id'); // AUTO_INCREMENT PRIMARY KEY
            $table->unsignedInteger('Campus_id');
            $table->unsignedInteger('College_id');
            $table->string('Abbreviation', 250);
            $table->string('Program_name', 250);
            $table->date('Created_at')->nullable();

            // Foreign Keys
            $table->foreign('Campus_id')->references('Campus_id')->on('add_campus')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('College_id')->references('College_id')->on('add_college')->onDelete('cascade')->onUpdate('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('program');
    }
}
