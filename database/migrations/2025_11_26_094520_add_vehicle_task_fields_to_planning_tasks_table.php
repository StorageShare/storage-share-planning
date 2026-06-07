<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('planning_tasks', function (Blueprint $table) {
            $table->foreignId('vehicle_task_id')->nullable()->after('default_task_id')->constrained('vehicle_tasks')->nullOnDelete();
            $table->boolean('is_vehicle_task')->default(false)->after('vehicle_task_id');
            if (! Schema::hasColumn('planning_tasks', 'estimated_time_minutes')) {
                $table->integer('estimated_time_minutes')->nullable()->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('planning_tasks', function (Blueprint $table) {
            if (Schema::hasColumn('planning_tasks', 'vehicle_task_id')) {
                $table->dropConstrainedForeignId('vehicle_task_id');
            }
            if (Schema::hasColumn('planning_tasks', 'is_vehicle_task')) {
                $table->dropColumn('is_vehicle_task');
            }
            if (Schema::hasColumn('planning_tasks', 'estimated_time_minutes')) {
                $table->dropColumn('estimated_time_minutes');
            }
        });
    }
};
