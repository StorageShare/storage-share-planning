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
        Schema::create('location_planning', function (Blueprint $table) {
            $table->foreignId('location_id')->constrained()->onDelete('cascade');
            $table->foreignId('planning_id')->constrained()->onDelete('cascade');
            $table->primary(['location_id', 'planning_id']);
            // $table->timestamps(); // Meestal niet nodig op een pure pivot tabel
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('location_planning');
    }
};
