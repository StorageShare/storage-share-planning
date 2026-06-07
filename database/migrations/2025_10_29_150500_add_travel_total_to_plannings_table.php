<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plannings', function (Blueprint $table) {
            if (! Schema::hasColumn('plannings', 'travel_time_distributed_total_seconds')) {
                $table->unsignedBigInteger('travel_time_distributed_total_seconds')->default(0)->after('travel_time_distributed_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('plannings', function (Blueprint $table) {
            if (Schema::hasColumn('plannings', 'travel_time_distributed_total_seconds')) {
                $table->dropColumn('travel_time_distributed_total_seconds');
            }
        });
    }
};
