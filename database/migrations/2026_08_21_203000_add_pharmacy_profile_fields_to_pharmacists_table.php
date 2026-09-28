<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pharmacists', function (Blueprint $table) {
            if (! Schema::hasColumn('pharmacists', 'pharmacy_name')) {
                $table->string('pharmacy_name')->nullable()->after('user_id');
            }
            if (! Schema::hasColumn('pharmacists', 'clinic_address')) {
                $table->string('clinic_address', 500)->nullable()->after('license_number');
            }
            if (! Schema::hasColumn('pharmacists', 'governorate_id')) {
                $table->unsignedBigInteger('governorate_id')->nullable()->after('clinic_address');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pharmacists', function (Blueprint $table) {
            if (Schema::hasColumn('pharmacists', 'governorate_id')) {
                $table->dropColumn('governorate_id');
            }
            if (Schema::hasColumn('pharmacists', 'clinic_address')) {
                $table->dropColumn('clinic_address');
            }
            if (Schema::hasColumn('pharmacists', 'pharmacy_name')) {
                $table->dropColumn('pharmacy_name');
            }
        });
    }
};
