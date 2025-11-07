<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('google_id')->nullable()->after('id');
            $table->string('role')->default('gebruiker')->after('password');
            $table->string('password')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Backfill any NULL passwords with a random unusable hash
        DB::table('users')
            ->whereNull('password')
            ->update([
                // Generate a random hash so these accounts can’t be logged into without reset
                'password' => Hash::make(bin2hex(random_bytes(16))),
            ]);

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('google_id');
            $table->dropColumn('role');
            $table->string('password')->nullable(false)->change();
        });
    }
};
