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
        Schema::create('planning_task_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('planning_task_id')->constrained('planning_tasks')->onDelete('cascade');
            $table->string('path'); // Pad naar de opgeslagen foto
            $table->string('original_name')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedInteger('size')->nullable(); // Grootte in bytes
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('planning_task_photos');
    }
};
