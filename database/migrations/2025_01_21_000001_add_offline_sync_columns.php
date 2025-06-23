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
        // Add sync_hash columns to existing tables
        Schema::table('planning_tasks', function (Blueprint $table) {
            $table->string('sync_hash')->nullable()->after('status');
            $table->index('sync_hash');
        });

        Schema::table('planning_task_completions', function (Blueprint $table) {
            $table->string('sync_hash')->nullable()->after('reviewed_by');
            $table->index('sync_hash');
        });

        Schema::table('planning_task_completion_photos', function (Blueprint $table) {
            $table->string('sync_hash')->nullable()->after('file_path');
            $table->index('sync_hash');
        });

        // Create offline sync queue table
        Schema::create('offline_sync_queue', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('entity_type', 50);
            $table->unsignedBigInteger('entity_id');
            $table->string('action', 20); // 'create', 'update', 'delete'
            $table->json('payload');
            $table->string('sync_hash')->unique();
            $table->integer('priority')->default(10);
            $table->integer('attempts')->default(0);
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'synced_at']);
            $table->index('sync_hash');
            $table->index(['entity_type', 'entity_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('offline_sync_queue');

        Schema::table('planning_task_completion_photos', function (Blueprint $table) {
            $table->dropIndex(['sync_hash']);
            $table->dropColumn('sync_hash');
        });

        Schema::table('planning_task_completions', function (Blueprint $table) {
            $table->dropIndex(['sync_hash']);
            $table->dropColumn('sync_hash');
        });

        Schema::table('planning_tasks', function (Blueprint $table) {
            $table->dropIndex(['sync_hash']);
            $table->dropColumn('sync_hash');
        });
    }
}; 