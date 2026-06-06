<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1) Allow 'open' in the ENUM and set it as the default.
        //    MySQL supports native ENUM redefinition; other drivers (e.g. SQLite
        //    used in tests) fall back to a portable string column change.
        if (DB::getDriverName() === 'mysql') {
            DB::statement(
                "ALTER TABLE `end_checklist_items` " .
                "MODIFY COLUMN `status` ENUM('open','pending','approved','rejected') " .
                "NOT NULL DEFAULT 'open'"
            );
        } else {
            Schema::table('end_checklist_items', function (Blueprint $table) {
                $table->string('status')->default('open')->nullable(false)->change();
            });
        }

        // 2) Backfill any empty values to 'open' for safety (should be rare)
        DB::table('end_checklist_items')
            ->whereNull('status')
            ->orWhere('status', '=', '')
            ->update(['status' => 'open']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Map any 'open' back to 'pending' before removing it from the ENUM
        DB::table('end_checklist_items')
            ->where('status', 'open')
            ->update(['status' => 'pending']);

        if (DB::getDriverName() === 'mysql') {
            DB::statement(
                "ALTER TABLE `end_checklist_items` " .
                "MODIFY COLUMN `status` ENUM('pending','approved','rejected') " .
                "NOT NULL DEFAULT 'pending'"
            );
        } else {
            Schema::table('end_checklist_items', function (Blueprint $table) {
                $table->string('status')->default('pending')->nullable(false)->change();
            });
        }
    }
};
