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
        Schema::table('tasks', function (Blueprint $table) {
            $table->string('room')->nullable()->after('location_id');
            $table->string('photo_process_step')->nullable()->after('status');
            $table->timestamp('photo_process_at')->nullable()->after('photo_process_step');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn(['room', 'photo_process_step', 'photo_process_at']);
        });
    }
};
