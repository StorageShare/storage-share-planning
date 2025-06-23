<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema; // Required for DB::raw

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            // Add the new integer column for minutes
            $table->integer('estimated_time_minutes')->nullable()->after('estimated_hours');
        });

        // Populate the new column from the old one
        // Ensure to handle potential NULL values in estimated_hours if they exist
        DB::table('tasks')->whereNotNull('estimated_hours')->update([
            'estimated_time_minutes' => DB::raw('ROUND(estimated_hours * 60)'),
        ]);
        // For any rows where estimated_hours was NULL, estimated_time_minutes will remain NULL or you can set a default
        DB::table('tasks')->whereNull('estimated_time_minutes')->update([
            'estimated_time_minutes' => 0, // Or handle as appropriate
        ]);

        Schema::table('tasks', function (Blueprint $table) {
            // Drop the old decimal column
            $table->dropColumn('estimated_hours');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            // Add back the old decimal column
            $table->decimal('estimated_hours', 8, 2)->nullable()->after('estimated_time_minutes');
        });

        // Populate the old column from the new one
        DB::table('tasks')->whereNotNull('estimated_time_minutes')->update([
            'estimated_hours' => DB::raw('estimated_time_minutes / 60.0'),
        ]);
        // For any rows where estimated_time_minutes was NULL (or 0 and you want NULL hours)
        // This depends on how you handled NULLs/0 in the up migration.
        // If 0 minutes should revert to NULL hours, add specific logic.
        // For now, assuming 0 minutes means 0.00 hours.

        Schema::table('tasks', function (Blueprint $table) {
            // Drop the new integer column
            $table->dropColumn('estimated_time_minutes');
        });
    }
};
