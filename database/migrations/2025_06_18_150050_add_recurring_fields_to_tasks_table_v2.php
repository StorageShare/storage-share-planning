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
            $table->boolean('is_recurring')->default(false);
            $table->enum('recurring_interval_type', ['days', 'weeks', 'months', 'years'])->nullable();
            $table->integer('recurring_interval_value')->nullable();
            $table->unsignedBigInteger('parent_recurring_task_id')->nullable();
            
            $table->foreign('parent_recurring_task_id')->references('id')->on('tasks')->onDelete('set null');
            $table->index('is_recurring');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropForeign(['parent_recurring_task_id']);
            $table->dropIndex(['is_recurring']);
            
            $table->dropColumn([
                'is_recurring',
                'recurring_interval_type',
                'recurring_interval_value',
                'parent_recurring_task_id'
            ]);
        });
    }
};
