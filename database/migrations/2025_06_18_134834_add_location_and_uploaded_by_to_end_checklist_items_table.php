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
        Schema::table('end_checklist_items', function (Blueprint $table) {
            $table->foreignId('location_id')->nullable()->after('planning_id')->constrained('locations')->onDelete('set null');
            $table->foreignId('uploaded_by')->nullable()->after('photo_path')->constrained('users')->onDelete('set null');
            $table->timestamp('uploaded_at')->nullable()->after('uploaded_by');
            
            $table->index(['location_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('end_checklist_items', function (Blueprint $table) {
            $table->dropForeign(['location_id']);
            $table->dropForeign(['uploaded_by']);
            $table->dropColumn(['location_id', 'uploaded_by', 'uploaded_at']);
        });
    }
};
