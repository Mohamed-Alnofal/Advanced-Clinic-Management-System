<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE orders MODIFY payment_status ENUM('pending', 'paid', 'cash_on_delivery', 'refunded') NOT NULL DEFAULT 'cash_on_delivery'");
    }

    public function down(): void
    {
        if (Schema::getConneسction()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE orders MODIFY payment_status ENUM('paid', 'cash_on_delivery') NOT NULL DEFAULT 'cash_on_delivery'");
    }
};
