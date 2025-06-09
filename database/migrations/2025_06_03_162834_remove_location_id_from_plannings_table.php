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
            // Eerst de foreign key constraint verwijderen
            // De naam van de constraint is meestal: table_column_foreign
            $table->dropForeign(['location_id']); // Of de expliciete naam als die anders is

            // Dan de kolom verwijderen
            $table->dropColumn('location_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('plannings', function (Blueprint $table) {
            // Voeg de kolom weer toe
            // Maak het nullable voor het geval er al data is zonder location_id, of geef een default.
            // Aangezien het een foreign key was, moet het type overeenkomen (unsignedBigInteger)
            $table->foreignId('location_id')->nullable()->constrained()->onDelete('cascade');
        });
    }
};
