<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Remove accidental duplicate doctor↔facility links before unique index.
        $dupes = DB::table('clinics')
            ->select('doctor_id', 'medical_facility_id', DB::raw('MIN(id) as keep_id'))
            ->groupBy('doctor_id', 'medical_facility_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($dupes as $d) {
            DB::table('clinics')
                ->where('doctor_id', $d->doctor_id)
                ->where('medical_facility_id', $d->medical_facility_id)
                ->where('id', '!=', $d->keep_id)
                ->delete();
        }

        Schema::table('clinics', function (Blueprint $table) {
            $table->unique(
                ['doctor_id', 'medical_facility_id'],
                'clinics_doctor_facility_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('clinics', function (Blueprint $table) {
            $table->dropUnique('clinics_doctor_facility_unique');
        });
    }
};
