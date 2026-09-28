<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // users.status was enum(active, accepted, rejected, suspended) — no "pending".
        DB::statement("ALTER TABLE users MODIFY COLUMN status ENUM('active','pending','accepted','rejected','suspended') NOT NULL DEFAULT 'active'");
    }

    public function down(): void
    {
        DB::table('users')->where('status', 'pending')->update(['status' => 'active']);
        DB::statement("ALTER TABLE users MODIFY COLUMN status ENUM('active','accepted','rejected','suspended') NOT NULL DEFAULT 'active'");
    }
};
