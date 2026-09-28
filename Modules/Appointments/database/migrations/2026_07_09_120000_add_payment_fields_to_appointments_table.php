<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            if (! Schema::hasColumn('appointments', 'consultation_fee')) {
                $table->decimal('consultation_fee', 10, 2)->nullable()->after('appointment_type');
            }
            if (! Schema::hasColumn('appointments', 'payment_hold_id')) {
                $table->foreignId('payment_hold_id')
                    ->nullable()
                    ->after('consultation_fee')
                    ->constrained('wallet_holds')
                    ->nullOnDelete();
            }
            if (! Schema::hasColumn('appointments', 'payment_method')) {
                $table->string('payment_method', 32)->default('none')->after('payment_hold_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            if (Schema::hasColumn('appointments', 'payment_hold_id')) {
                $table->dropConstrainedForeignId('payment_hold_id');
            }
            if (Schema::hasColumn('appointments', 'consultation_fee')) {
                $table->dropColumn('consultation_fee');
            }
            if (Schema::hasColumn('appointments', 'payment_method')) {
                $table->dropColumn('payment_method');
            }
        });
    }
};
