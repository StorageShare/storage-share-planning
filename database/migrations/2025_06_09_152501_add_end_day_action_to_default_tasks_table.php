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
            $table->string('end_day_action_title')->nullable()->after('estimated_time_minutes');
            $table->text('end_day_action_description')->nullable()->after('end_day_action_title');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('default_tasks', function (Blueprint $table) {
            $table->dropColumn(['end_day_action_title', 'end_day_action_description']);
        });
    }
};
