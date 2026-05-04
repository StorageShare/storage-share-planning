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
        Schema::table('plannings', function (Blueprint $table) {
            $table->boolean('check_inactive_spaces')->default(false)->after('internal_notes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('plannings', function (Blueprint $table) {
            $table->dropColumn('check_inactive_spaces');
        });
    }
};
