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
            $table->string('intratone_number')->nullable()->after('indoor_safe_content')->comment('Intratone nummer');
            $table->text('intratone_multiple_numbers')->nullable()->after('intratone_number')->comment('Meerdere intratone nummers');
            $table->string('gate_number')->nullable()->after('intratone_multiple_numbers')->comment('Hek nummer');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->dropColumn([
                'intratone_number',
                'intratone_multiple_numbers',
                'gate_number',
            ]);
        });
    }
}; 