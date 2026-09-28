<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('doctors', function (Blueprint $table) {
            if (! Schema::hasColumn('doctors', 'governorate_id')) {
                $table->foreignId('governorate_id')
                    ->nullable()
                    ->after('specialization_id')
                    ->constrained('governorates')
                    ->nullOnDelete();
            }
            if (! Schema::hasColumn('doctors', 'clinic_name')) {
                $table->string('clinic_name')->nullable()->after('image');
            }
            if (! Schema::hasColumn('doctors', 'clinic_address')) {
                $table->string('clinic_address')->nullable()->after('clinic_name');
            }
            if (! Schema::hasColumn('doctors', 'clinic_latitude')) {
                $table->decimal('clinic_latitude', 10, 7)->nullable()->after('clinic_address');
            }
            if (! Schema::hasColumn('doctors', 'clinic_longitude')) {
                $table->decimal('clinic_longitude', 10, 7)->nullable()->after('clinic_latitude');
            }
            if (! Schema::hasColumn('doctors', 'price')) {
                $table->decimal('price', 10, 2)->nullable()->after('experience_years');
            }
            if (! Schema::hasColumn('doctors', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('is_verified');
            }
        });
    }

    public function down(): void
    {
        Schema::table('doctors', function (Blueprint $table) {
            if (Schema::hasColumn('doctors', 'governorate_id')) {
                $table->dropConstrainedForeignId('governorate_id');
            }
            foreach (['clinic_name', 'clinic_address', 'clinic_latitude', 'clinic_longitude', 'price', 'is_active'] as $col) {
                if (Schema::hasColumn('doctors', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
