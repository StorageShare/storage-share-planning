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
            if (!Schema::hasColumn('plannings', 'vehicle_id')) {
                $table->foreignId('vehicle_id')
                    ->nullable()
                    ->constrained('vehicles')
                    ->cascadeOnUpdate()
                    ->nullOnDelete();
                $table->index(['vehicle_id', 'planned_date']);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('plannings', function (Blueprint $table) {
            if (Schema::hasColumn('plannings', 'vehicle_id')) {
                $table->dropIndex(['vehicle_id', 'planned_date']);
                $table->dropConstrainedForeignId('vehicle_id');
            }
        });
    }
};
