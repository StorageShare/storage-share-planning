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
        Schema::table('planning_user', function (Blueprint $table) {
            $table->timestamp('notification_sent_at')->nullable()->after('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('planning_user', function (Blueprint $table) {
            $table->dropColumn('notification_sent_at');
        });
    }
};
