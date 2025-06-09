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
        Schema::table('locations', function (Blueprint $table) {
            $table->unsignedBigInteger('external_id')->nullable()->unique()->after('id')->comment('ID from the external API');
            $table->timestamp('last_synced_at')->nullable()->after('description')->comment('Timestamp of the last sync from API');

            // Make existing columns nullable if they weren't already
            // Important: This assumes these columns exist.
            // If 'address' or 'description' were added in later migrations and might not exist when this runs,
            // add Schema::hasColumn checks or ensure correct migration order.
            if (Schema::hasColumn('locations', 'address')) {
                $table->string('address')->nullable()->change();
            }
            if (Schema::hasColumn('locations', 'description')) {
                $table->text('description')->nullable()->change(); // Assuming description can be longer
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            if (Schema::hasColumn('locations', 'external_id')) {
                // To properly drop a unique constraint before dropping the column,
                // the index name is needed, which is usually tablename_columnname_unique.
                // $table->dropUnique('locations_external_id_unique'); // Or whatever the generated name is
                $table->dropColumn('external_id');
            }
            if (Schema::hasColumn('locations', 'last_synced_at')) {
                $table->dropColumn('last_synced_at');
            }

            // Reverting nullable changes is tricky without knowing the original state.
            // If they were NOT nullable, this would attempt to set them back.
            // This might fail if there's data that is NULL.
            // For simplicity, we'll assume they remain nullable on rollback,
            // or that the user handles data integrity before rollback.
            // If 'address' was originally not nullable:
            // if (Schema::hasColumn('locations', 'address')) {
            //     $table->string('address')->nullable(false)->change();
            // }
            // if (Schema::hasColumn('locations', 'description')) {
            //    $table->text('description')->nullable(false)->change();
            // }
        });
    }
};
