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
            if (! Schema::hasColumn('plannings', 'travel_time_distributed_at')) {
                $table->timestamp('travel_time_distributed_at')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('plannings', function (Blueprint $table) {
            if (Schema::hasColumn('plannings', 'travel_time_distributed_at')) {
                $table->dropColumn('travel_time_distributed_at');
            }
        });
    }
};
