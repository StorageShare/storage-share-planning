<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('planning_task_completions', function (Blueprint $table) {
            $table->timestamp('reviewed_at')->nullable()->after('review_notes');
            $table->string('review_outcome')->nullable()->after('reviewed_at'); // e.g., 'approved', 'rejected'
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('planning_task_completions', function (Blueprint $table) {
            $table->dropColumn(['reviewed_at', 'review_outcome']);
        });
    }
};
