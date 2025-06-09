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
            $table->integer('estimated_time_minutes')->nullable()->default(0)->after('description');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('default_tasks', function (Blueprint $table) {
            $table->dropColumn('estimated_time_minutes');
        });
    }
};
