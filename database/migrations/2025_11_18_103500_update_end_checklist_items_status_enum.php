<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1) Allow 'open' in the ENUM and set it as the default
        DB::statement(
            "ALTER TABLE `end_checklist_items` " .
            "MODIFY COLUMN `status` ENUM('open','pending','approved','rejected') " .
            "NOT NULL DEFAULT 'open'"
        );

        // 2) Backfill any empty values to 'open' for safety (should be rare)
        DB::table('end_checklist_items')
            ->whereNull('status')
            ->orWhere('status', '=','')
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

        DB::statement(
            "ALTER TABLE `end_checklist_items` " .
            "MODIFY COLUMN `status` ENUM('pending','approved','rejected') " .
            "NOT NULL DEFAULT 'pending'"
        );
    }
};
