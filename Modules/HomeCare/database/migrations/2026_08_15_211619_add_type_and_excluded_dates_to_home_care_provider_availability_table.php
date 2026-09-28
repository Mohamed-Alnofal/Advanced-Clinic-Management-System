<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('home_care_provider_availability', function (Blueprint $table) {
            $table->string('type')
                ->default('weekly')
                ->after('home_care_profile_id');

            $table->json('excluded_dates')
                ->nullable()
                ->after('end_date');
        });
    }

    public function down(): void
    {
        Schema::table('home_care_provider_availability', function (Blueprint $table) {
            $table->dropColumn([
                'type',
                'excluded_dates',
            ]);
        });
    }
};