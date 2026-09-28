<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // إضافة pharmacy_id (nullable أولاً)
            if (!Schema::hasColumn('orders', 'pharmacy_id')) {
                $table->unsignedBigInteger('pharmacy_id')->nullable()->after('user_id');
                $table->foreign('pharmacy_id')->references('id')->on('pharmacies');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'pharmacy_id')) {
                $table->dropForeign(['pharmacy_id']);
                $table->dropColumn('pharmacy_id');
            }
        });
    }
};