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
        Schema::create('end_checklist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('planning_id')->constrained()->onDelete('cascade');
            $table->enum('type', ['material', 'end_action']); // Type van checklist item
            $table->foreignId('benodigdheid_id')->nullable()->constrained('benodigdheden')->onDelete('set null'); // Voor materiaal items
            $table->string('title'); // Voor end action items, of materiaal naam
            $table->text('description')->nullable(); // Extra beschrijving
            $table->string('photo_path')->nullable(); // Pad naar bewijs foto
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('admin_notes')->nullable(); // Admin opmerkingen bij beoordeling
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            
            $table->index(['planning_id', 'type']);
            $table->index(['status', 'planning_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('end_checklist_items');
    }
};
