<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 0) (Optional) normalize column name if your original used "User_Id"
        if (Schema::hasColumn('user_designation', 'User_Id') && !Schema::hasColumn('user_designation', 'User_id')) {
            Schema::table('user_designation', function (Blueprint $table) {
                $table->renameColumn('User_Id', 'User_id');
            });
        }

        // 1) Drop old FKs if they still reference legacy tables or have wrong actions
        //    Adjust names if yours differ (check phpMyAdmin Relation view / Structure > Indexes)
        $fkNames = [
            'fk_user_designation_campus',
            'fk_user_designation_college',
            'fk_user_designation_program',
            'fk_user_designation_major',
            'fk_login_id',
            // If you had legacy names like "Program_id", include them too:
            // 'Program_id',
        ];

        foreach ($fkNames as $fk) {
            try {
                DB::statement("ALTER TABLE user_designation DROP FOREIGN KEY `$fk`");
            } catch (\Throwable $e) {
                // ignore if it doesn't exist
            }
        }

        // 2) Make scope columns nullable
        Schema::table('user_designation', function (Blueprint $table) {
            // some MySQL versions require integer type be unchanged; we only flip nullability
            $table->integer('Campus_id')->nullable()->change();
            $table->integer('College_id')->nullable()->change();
            $table->integer('Program_id')->nullable()->change();
            // add Major_id/Login_id if they exist
            if (Schema::hasColumn('user_designation', 'Major_id')) {
                $table->integer('Major_id')->nullable()->change();
            }
            if (Schema::hasColumn('user_designation', 'Login_id')) {
                $table->integer('Login_id')->nullable()->change();
            }
        });

        // 3) Recreate FKs to the CURRENT tables with safe actions
        // NOTE: If your PKs are unsigned bigints, change INT to UNSIGNED BIGINT in step 2 above.
        // Campus/College/Program/Major/Login -> SET NULL on delete (scope becomes broader instead of breaking)
        try {
            DB::statement('ALTER TABLE user_designation
                ADD CONSTRAINT fk_user_designation_campus
                FOREIGN KEY (Campus_id) REFERENCES campus(Campus_id)
                ON DELETE SET NULL ON UPDATE CASCADE');
        } catch (\Throwable $e) {}

        try {
            DB::statement('ALTER TABLE user_designation
                ADD CONSTRAINT fk_user_designation_college
                FOREIGN KEY (College_id) REFERENCES college(College_id)
                ON DELETE SET NULL ON UPDATE CASCADE');
        } catch (\Throwable $e) {}

        try {
            DB::statement('ALTER TABLE user_designation
                ADD CONSTRAINT fk_user_designation_program
                FOREIGN KEY (Program_id) REFERENCES program(Program_id)
                ON DELETE SET NULL ON UPDATE CASCADE');
        } catch (\Throwable $e) {}

        if (Schema::hasColumn('user_designation', 'Major_id')) {
            try {
                DB::statement('ALTER TABLE user_designation
                    ADD CONSTRAINT fk_user_designation_major
                    FOREIGN KEY (Major_id) REFERENCES major(Major_id)
                    ON DELETE SET NULL ON UPDATE CASCADE');
            } catch (\Throwable $e) {}
        }

        if (Schema::hasColumn('user_designation', 'Login_id')) {
            try {
                DB::statement('ALTER TABLE user_designation
                    ADD CONSTRAINT fk_login_id
                    FOREIGN KEY (Login_id) REFERENCES login(Login_id)
                    ON DELETE SET NULL ON UPDATE CASCADE');
            } catch (\Throwable $e) {}
        }

        // 4) (Optional) If you want strict FKs for the always-required ones:
        //    Keep as-is if they already exist and point to correct tables.
        //    Otherwise re-add:
        // DB::statement('ALTER TABLE user_designation
        //     ADD CONSTRAINT fk_user_designation_designation
        //     FOREIGN KEY (Designation_id) REFERENCES designation(Designation_id)
        //     ON DELETE CASCADE ON UPDATE CASCADE');
        //
        // DB::statement('ALTER TABLE user_designation
        //     ADD CONSTRAINT fk_user_designation_user
        //     FOREIGN KEY (User_id) REFERENCES user_manage(User_id)
        //     ON DELETE CASCADE ON UPDATE CASCADE');
        //
        // If your users table is `signup`, adjust accordingly.
    }

    public function down(): void
    {
        // You can leave down() empty or attempt to revert
        // For safety we won't try to restore the old broken constraints.
    }
};
