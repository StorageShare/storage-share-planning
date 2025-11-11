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
        Schema::create('benodigdheid_location', function (Blueprint $table) {
            $table->id();
            $table->foreignId('benodigdheid_id')->constrained('benodigdheden')->onDelete('cascade');
            $table->foreignId('location_id')->constrained('locations')->onDelete('cascade');
            $table->timestamps();

            $table->unique(['benodigdheid_id', 'location_id']);
            $table->index(['benodigdheid_id']);
            $table->index(['location_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('benodigdheid_location');
    }
};
