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
            $table->dropColumn([
                'address',
                'postal_code',
                'city',
                'description',
                'outdoor_safe_code',
                'indoor_safe_code',
                'outdoor_safe_content',
                'indoor_safe_content',
                'intratone_number',
                'intratone_multiple_numbers',
                'gate_number',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->string('address')->nullable()->after('name');
            $table->string('postal_code')->nullable()->after('address');
            $table->string('city')->nullable()->after('postal_code');
            $table->string('description')->nullable()->after('city');
            $table->string('outdoor_safe_code')->nullable()->after('description');
            $table->string('indoor_safe_code')->nullable()->after('outdoor_safe_code');
            $table->text('outdoor_safe_content')->nullable()->after('indoor_safe_code');
            $table->text('indoor_safe_content')->nullable()->after('outdoor_safe_content');
            $table->string('intratone_number')->nullable()->after('indoor_safe_content');
            $table->text('intratone_multiple_numbers')->nullable()->after('intratone_number');
            $table->string('gate_number')->nullable()->after('intratone_multiple_numbers');
            $table->string('description')->nullable()->after('gate_number');
        });
    }
};
