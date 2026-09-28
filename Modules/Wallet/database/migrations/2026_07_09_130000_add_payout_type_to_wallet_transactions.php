<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('wallet_transactions') || ! Schema::hasColumn('wallet_transactions', 'type')) {
            return;
        }

        DB::statement(
            "ALTER TABLE wallet_transactions MODIFY type ENUM('deposit','withdraw','hold','refund','payment','payout') NOT NULL"
        );
    }

    public function down(): void
    {
        if (! Schema::hasTable('wallet_transactions') || ! Schema::hasColumn('wallet_transactions', 'type')) {
            return;
        }

        DB::statement(
            "ALTER TABLE wallet_transactions MODIFY type ENUM('deposit','withdraw','hold','refund','payment') NOT NULL"
        );
    }
};
