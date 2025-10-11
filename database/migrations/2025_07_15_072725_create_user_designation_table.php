<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUserDesignationTable extends Migration
{
    public function up()
    {
        Schema::create('user_designation', function (Blueprint $table) {
            $table->id('UserDesignation_id');
            
            $table->unsignedBigInteger('Campus_id');
            $table->unsignedBigInteger('College_id');
            $table->unsignedBigInteger('Program_id');
            $table->unsignedBigInteger('Designation_id');
            $table->unsignedBigInteger('User_Id'); // ✅ Added Login_id column

            // 🔗 Foreign Keys
            $table->foreign('Campus_id')
                  ->references('Campus_id')->on('add_campus')
                  ->onDelete('cascade')
                  ->onUpdate('cascade');

            $table->foreign('College_id')
                  ->references('College_id')->on('add_college')
                  ->onDelete('cascade')
                  ->onUpdate('cascade');

            $table->foreign('Program_id')
                  ->references('Program_id')->on('add_program')
                  ->onDelete('cascade')
                  ->onUpdate('cascade');

            $table->foreign('Designation_id')
                  ->references('Designation_id')->on('designation')
                  ->onDelete('cascade')
                  ->onUpdate('cascade');

            $table->foreign('User_Id')
                  ->references('User_id')->on('signup')
                  ->onDelete('cascade')
                  ->onUpdate('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('user_designation');
    }
}
