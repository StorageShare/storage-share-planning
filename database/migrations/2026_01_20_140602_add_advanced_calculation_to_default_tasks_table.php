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
        Schema::table('default_tasks', function (Blueprint $table) {
            $table->string('time_calculation_type')->default('simplified')->after('estimated_time_minutes');
            $table->decimal('time_per_m2_minutes', 8, 2)->nullable()->after('time_calculation_type');
            $table->integer('base_time_minutes')->nullable()->after('time_per_m2_minutes');
            $table->integer('has_lift_extra_minutes')->nullable()->after('base_time_minutes');
            $table->integer('no_lift_extra_minutes')->nullable()->after('has_lift_extra_minutes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('default_tasks', function (Blueprint $table) {
            $table->dropColumn([
                'time_calculation_type',
                'time_per_m2_minutes',
                'base_time_minutes',
                'has_lift_extra_minutes',
                'no_lift_extra_minutes',
            ]);
        });
    }
};
