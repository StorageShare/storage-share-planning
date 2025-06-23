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
        Schema::create('default_task_benodigdheden', function (Blueprint $table) {
            $table->id();
            $table->foreignId('default_task_id')->constrained()->onDelete('cascade');
            $table->foreignId('benodigdheid_id')->constrained('benodigdheden')->onDelete('cascade');
            $table->timestamps();

            $table->unique(['default_task_id', 'benodigdheid_id'], 'def_task_benodigdheid_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('default_task_benodigdheden');
    }
};
