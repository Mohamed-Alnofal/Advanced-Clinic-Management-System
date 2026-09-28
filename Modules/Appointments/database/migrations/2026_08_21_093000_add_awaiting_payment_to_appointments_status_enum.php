<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('appointments')) {
            return;
        }

        // status was enum(pending, accepted, rejected, cancelled, completed).
        // Payment flow needs awaiting_payment before wallet/stripe settles.
        DB::statement("ALTER TABLE appointments MODIFY COLUMN status ENUM(
            'pending',
            'awaiting_payment',
            'accepted',
            'rejected',
            'cancelled',
            'completed'
        ) NOT NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        if (! Schema::hasTable('appointments')) {
            return;
        }

        DB::table('appointments')
            ->where('status', 'awaiting_payment')
            ->update(['status' => 'pending']);

        DB::statement("ALTER TABLE appointments MODIFY COLUMN status ENUM(
            'pending',
            'accepted',
            'rejected',
            'cancelled',
            'completed'
        ) NOT NULL DEFAULT 'pending'");
    }
};
