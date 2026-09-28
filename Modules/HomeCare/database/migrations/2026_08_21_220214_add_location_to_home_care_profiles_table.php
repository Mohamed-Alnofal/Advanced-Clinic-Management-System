<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(
            'home_care_profiles',
            function (Blueprint $table) {

                $table->decimal(
                    'latitude',
                    10,
                    8
                )
                    ->nullable()
                    ->after('service_radius_km');

                $table->decimal(
                    'longitude',
                    11,
                    8
                )
                    ->nullable()
                    ->after('latitude');

                $table->timestamp(
                    'location_updated_at'
                )
                    ->nullable()
                    ->after('longitude');
            }
        );
    }

    public function down(): void
    {
        Schema::table(
            'home_care_profiles',
            function (Blueprint $table) {

                $table->dropColumn([
                    'latitude',
                    'longitude',
                    'location_updated_at',
                ]);
            }
        );
    }
};