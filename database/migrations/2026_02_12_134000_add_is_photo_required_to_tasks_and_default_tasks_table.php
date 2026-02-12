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
            $table->boolean('is_photo_required')->default(false)->after('feedback_information');
        });

        Schema::table('default_tasks', function (Blueprint $table) {
            $table->boolean('is_photo_required')->default(false)->after('feedback_information');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn('is_photo_required');
        });

        Schema::table('default_tasks', function (Blueprint $table) {
            $table->dropColumn('is_photo_required');
        });
    }
};
