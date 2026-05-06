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
        Schema::table('location_planning', function (Blueprint $table) {
            $table->boolean('check_inactive_spaces')->default(false);
        });

        // Copy existing data if any (though unlikely to have much data yet given the recent feature)
        $plannings = \DB::table('plannings')->where('check_inactive_spaces', true)->get();
        foreach ($plannings as $planning) {
            \DB::table('location_planning')
                ->where('planning_id', $planning->id)
                ->update(['check_inactive_spaces' => true]);
        }

        Schema::table('plannings', function (Blueprint $table) {
            $table->dropColumn('check_inactive_spaces');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('plannings', function (Blueprint $table) {
            $table->boolean('check_inactive_spaces')->default(false);
        });

        // Try to restore data (if all locations in a planning had it checked, set it back on planning)
        // This is a bit lossy but best effort.
        $planningIds = \DB::table('location_planning')
            ->where('check_inactive_spaces', true)
            ->distinct()
            ->pluck('planning_id');

        foreach ($planningIds as $planningId) {
            \DB::table('plannings')->where('id', $planningId)->update(['check_inactive_spaces' => true]);
        }

        Schema::table('location_planning', function (Blueprint $table) {
            $table->dropColumn('check_inactive_spaces');
        });
    }
};
