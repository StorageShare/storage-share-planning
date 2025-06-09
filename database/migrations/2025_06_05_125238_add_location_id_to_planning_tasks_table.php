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
        Schema::table('planning_tasks', function (Blueprint $table) {
            $table->foreignId('location_id')->nullable()->after('default_task_id')->constrained()->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('planning_tasks', function (Blueprint $table) {
            // Drop foreign key first if named, or by columns if not specifically named in creation
            // Assuming default naming convention: planning_tasks_location_id_foreign
            $table->dropForeign(['location_id']);
            $table->dropColumn('location_id');
        });
    }
};
