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
        Schema::create('external_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')->constrained()->onDelete('cascade');
            $table->string('title');
            $table->text('description')->nullable();
            $table->text('feedback_information')->nullable();
            $table->dateTime('external_deadline_at')->nullable();
            $table->integer('estimated_time_minutes')->nullable();
            $table->string('status');
            $table->string('priority');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('external_tasks');
    }
};
