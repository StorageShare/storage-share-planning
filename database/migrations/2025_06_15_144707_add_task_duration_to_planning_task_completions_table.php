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
            $table->integer('task_duration_seconds')->nullable()->after('comment');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('planning_task_completions', function (Blueprint $table) {
            $table->dropColumn('task_duration_seconds');
        });
    }
};
