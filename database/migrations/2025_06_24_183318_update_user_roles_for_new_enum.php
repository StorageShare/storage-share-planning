<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Update bestaande 'user' rollen naar 'gebruiker'
        DB::table('users')
            ->where('role', 'user')
            ->update(['role' => 'gebruiker']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Terug naar 'user' voor rollback
        DB::table('users')
            ->where('role', 'gebruiker')
            ->update(['role' => 'user']);
    }
};
