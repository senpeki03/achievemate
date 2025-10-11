<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('graduation_requirement_types', function (Blueprint $t) {
            $t->id();
            $t->string('code')->unique(); // e.g., FORM, ACCTG_CLR, REG_CLR, LIB_CLR, THESIS_HARDBOUND
            $t->string('name');           // human label
            $t->boolean('is_file_required')->default(true); // some may be checkbox only
            $t->unsignedInteger('sort')->default(0);
            $t->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('graduation_requirement_types');
    }
};
