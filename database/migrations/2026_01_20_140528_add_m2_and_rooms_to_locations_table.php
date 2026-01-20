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
            $table->decimal('total_m2_net', 10, 2)->nullable()->after('type_deur');
            $table->decimal('total_m2_gross', 10, 2)->nullable()->after('total_m2_net');
            $table->integer('total_rooms')->nullable()->after('total_m2_gross');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->dropColumn([
                'total_m2_net',
                'total_m2_gross',
                'total_rooms',
            ]);
        });
    }
};
