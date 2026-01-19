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
        Schema::table('tasks', function (Blueprint $table) {
            $table->string('feedback_information')->nullable()->after('description');
        });

        Schema::table('default_tasks', function (Blueprint $table) {
            $table->string('feedback_information')->nullable()->after('description');
        });

        Schema::table('planning_tasks', function (Blueprint $table) {
            $table->string('feedback_information')->nullable()->after('description');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn('feedback_information');
        });

        Schema::table('default_tasks', function (Blueprint $table) {
            $table->dropColumn('feedback_information');
        });

        Schema::table('planning_tasks', function (Blueprint $table) {
            $table->dropColumn('feedback_information');
        });
    }
};
