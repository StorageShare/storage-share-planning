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
            $table->string('type_deur')->nullable()->after('last_synced_at')->comment('Type van de deur');
            $table->string('outdoor_safe_code')->nullable()->after('type_deur')->comment('Code voor buitenkluis');
            $table->string('indoor_safe_code')->nullable()->after('outdoor_safe_code')->comment('Code voor binnenkluis');
            $table->text('outdoor_safe_content')->nullable()->after('indoor_safe_code')->comment('Inhoud van buitenkluis');
            $table->text('indoor_safe_content')->nullable()->after('outdoor_safe_content')->comment('Inhoud van binnenkluis');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->dropColumn([
                'type_deur',
                'outdoor_safe_code',
                'indoor_safe_code',
                'outdoor_safe_content',
                'indoor_safe_content',
            ]);
        });
    }
}; 