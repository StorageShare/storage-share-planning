<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1) Rename primary table
        if (Schema::hasTable('benodigdheden')) {
            Schema::rename('benodigdheden', 'requirements');
        }

        // 2) Rename columns on requirements table
        if (Schema::hasTable('requirements')) {
            Schema::table('requirements', function (Blueprint $table) {
                if (Schema::hasColumn('requirements', 'naam')) {
                    $table->renameColumn('naam', 'name');
                }
                if (Schema::hasColumn('requirements', 'beschrijving')) {
                    $table->renameColumn('beschrijving', 'description');
                }
            });
        }

        // 3) Update FKs that reference benodigdheid to requirement
        // Pivot: task_benodigdheden
        if (Schema::hasTable('task_benodigdheden')) {
            Schema::table('task_benodigdheden', function (Blueprint $table) {
                if (Schema::hasColumn('task_benodigdheden', 'benodigdheid_id')) {
                    $table->renameColumn('benodigdheid_id', 'requirement_id');
                }
            });
        }

        // Pivot: default_task_benodigdheden
        if (Schema::hasTable('default_task_benodigdheden')) {
            Schema::table('default_task_benodigdheden', function (Blueprint $table) {
                if (Schema::hasColumn('default_task_benodigdheden', 'benodigdheid_id')) {
                    $table->renameColumn('benodigdheid_id', 'requirement_id');
                }
            });
        }

        // Pivot: benodigdheid_location
        if (Schema::hasTable('benodigdheid_location')) {
            Schema::table('benodigdheid_location', function (Blueprint $table) {
                if (Schema::hasColumn('benodigdheid_location', 'benodigdheid_id')) {
                    $table->renameColumn('benodigdheid_id', 'requirement_id');
                }
            });
        }

        // end_checklist_items
        if (Schema::hasTable('end_checklist_items')) {
            Schema::table('end_checklist_items', function (Blueprint $table) {
                if (Schema::hasColumn('end_checklist_items', 'benodigdheid_id')) {
                    $table->renameColumn('benodigdheid_id', 'requirement_id');
                }
            });
        }
    }

    public function down(): void
    {
        // Reverse end_checklist_items
        if (Schema::hasTable('end_checklist_items')) {
            Schema::table('end_checklist_items', function (Blueprint $table) {
                if (Schema::hasColumn('end_checklist_items', 'requirement_id')) {
                    $table->renameColumn('requirement_id', 'benodigdheid_id');
                }
            });
        }

        // Reverse pivots
        if (Schema::hasTable('benodigdheid_location')) {
            Schema::table('benodigdheid_location', function (Blueprint $table) {
                if (Schema::hasColumn('benodigdheid_location', 'requirement_id')) {
                    $table->renameColumn('requirement_id', 'benodigdheid_id');
                }
            });
        }
        if (Schema::hasTable('default_task_benodigdheden')) {
            Schema::table('default_task_benodigdheden', function (Blueprint $table) {
                if (Schema::hasColumn('default_task_benodigdheden', 'requirement_id')) {
                    $table->renameColumn('requirement_id', 'benodigdheid_id');
                }
            });
        }
        if (Schema::hasTable('task_benodigdheden')) {
            Schema::table('task_benodigdheden', function (Blueprint $table) {
                if (Schema::hasColumn('task_benodigdheden', 'requirement_id')) {
                    $table->renameColumn('requirement_id', 'benodigdheid_id');
                }
            });
        }

        // Reverse requirements table columns
        if (Schema::hasTable('requirements')) {
            Schema::table('requirements', function (Blueprint $table) {
                if (Schema::hasColumn('requirements', 'name')) {
                    $table->renameColumn('name', 'naam');
                }
                if (Schema::hasColumn('requirements', 'description')) {
                    $table->renameColumn('description', 'beschrijving');
                }
            });
        }

        // Rename table back
        if (Schema::hasTable('requirements')) {
            Schema::rename('requirements', 'benodigdheden');
        }
    }
};
