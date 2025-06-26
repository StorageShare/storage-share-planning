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
        Schema::create('planning_location_timers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('planning_id')->constrained()->onDelete('cascade');
            $table->foreignId('location_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('location_type')->default('location'); // 'location', 'travel', 'backlog'
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->integer('total_duration_seconds')->default(0);
            $table->timestamps();
            
            // Index voor betere prestaties
            $table->index(['planning_id', 'location_id', 'location_type'], 'plt_planning_location_type_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('planning_location_timers');
    }
};
