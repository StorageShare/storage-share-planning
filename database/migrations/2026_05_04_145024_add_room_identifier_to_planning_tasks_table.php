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
            $table->string('room_identifier')->nullable()->after('is_vehicle_task');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('planning_tasks', function (Blueprint $table) {
            $table->dropColumn('room_identifier');
        });
    }
};
