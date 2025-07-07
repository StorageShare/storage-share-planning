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
        Schema::create('location_distances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('from_location_id')->constrained('locations')->onDelete('cascade');
            $table->foreignId('to_location_id')->constrained('locations')->onDelete('cascade');
            $table->decimal('distance_km', 8, 2)->nullable(); // Afstand in kilometers
            $table->integer('duration_minutes')->nullable(); // Reistijd in minuten
            $table->timestamp('calculated_at')->nullable(); // Wanneer berekend
            $table->string('calculation_method', 50)->default('google_maps'); // Hoe berekend (google_maps, manual, etc)
            $table->json('api_response')->nullable(); // Volledige API response voor debugging
            $table->timestamps();

            // Unique constraint - voorkoom duplicates voor zelfde locatie paar
            $table->unique(['from_location_id', 'to_location_id'], 'unique_location_pair');
            
            // Index voor snelle lookups
            $table->index(['from_location_id', 'to_location_id']);
            $table->index(['to_location_id', 'from_location_id']);
            $table->index('calculated_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('location_distances');
    }
};
