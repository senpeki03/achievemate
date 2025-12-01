<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_types', function (Blueprint $table) {
            $table->integer('EventType_id')->autoIncrement();
            $table->string('type_name', 150);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_types');
    }
};
