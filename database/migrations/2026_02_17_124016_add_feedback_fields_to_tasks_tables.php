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
        $tables = ['tasks', 'default_tasks', 'planning_tasks', 'external_tasks'];

        foreach ($tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->string('feedback_owner_name')->nullable()->after('feedback_information');
                $table->string('feedback_emails')->nullable()->after('feedback_owner_name');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = ['tasks', 'default_tasks', 'planning_tasks', 'external_tasks'];

        foreach ($tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn(['feedback_owner_name', 'feedback_emails']);
            });
        }
    }
};
