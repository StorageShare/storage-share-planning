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
        Schema::create('planning_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('planning_id')->constrained()->onDelete('cascade');
            $table->foreignId('task_id')->nullable()->constrained()->onDelete('set null'); // Ad-hoc task
            $table->foreignId('default_task_id')->nullable()->constrained()->onDelete('set null'); // Task from default tasks

            $table->string('title'); // Denormalized/copied from original task or default task
            $table->text('description'); // Denormalized/copied

            $table->dateTime('completed_at')->nullable();
            $table->text('completed_notes')->nullable();
            $table->timestamps();

            // It might be good to add a check constraint to ensure either task_id or default_task_id is set, but not both.
            // This can also be handled at the application level.
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('planning_tasks');
    }
};
