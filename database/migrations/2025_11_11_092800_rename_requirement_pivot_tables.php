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
        // Rename pivot tables if they exist
        if (Schema::hasTable('benodigdheid_location') && ! Schema::hasTable('requirement_location')) {
            Schema::rename('benodigdheid_location', 'requirement_location');
        }

        if (Schema::hasTable('task_benodigdheden') && ! Schema::hasTable('task_requirements')) {
            Schema::rename('task_benodigdheden', 'task_requirements');
        }

        if (Schema::hasTable('default_task_benodigdheden') && ! Schema::hasTable('default_task_requirements')) {
            Schema::rename('default_task_benodigdheden', 'default_task_requirements');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('requirement_location') && ! Schema::hasTable('benodigdheid_location')) {
            Schema::rename('requirement_location', 'benodigdheid_location');
        }

        if (Schema::hasTable('task_requirements') && ! Schema::hasTable('task_benodigdheden')) {
            Schema::rename('task_requirements', 'task_benodigdheden');
        }

        if (Schema::hasTable('default_task_requirements') && ! Schema::hasTable('default_task_benodigdheden')) {
            Schema::rename('default_task_requirements', 'default_task_benodigdheden');
        }
    }
};
