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
        Schema::table('planning_task_completion_photos', function (Blueprint $table) {
            $table->string('room')->nullable()->after('completion_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('planning_task_completion_photos', function (Blueprint $table) {
            $table->dropColumn('room');
        });
    }
};
