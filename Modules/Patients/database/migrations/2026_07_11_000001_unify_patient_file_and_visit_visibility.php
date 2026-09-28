<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Enforce one unified chart per patient + normalize visit visibility enums.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Merge duplicate charts into the oldest file before unique index.
        $duplicates = DB::table('patient_files')
            ->select('patient_id', DB::raw('MIN(id) as keep_id'), DB::raw('COUNT(*) as c'))
            ->groupBy('patient_id')
            ->having('c', '>', 1)
            ->get();

        foreach ($duplicates as $row) {
            $extraIds = DB::table('patient_files')
                ->where('patient_id', $row->patient_id)
                ->where('id', '!=', $row->keep_id)
                ->pluck('id');

            foreach (['medical_visits', 'vital_signs', 'surgeries', 'family_histories', 'ocular_histories', 'patient_access_requests'] as $table) {
                if (Schema::hasTable($table) && Schema::hasColumn($table, 'patient_file_id')) {
                    DB::table($table)
                        ->whereIn('patient_file_id', $extraIds)
                        ->update(['patient_file_id' => $row->keep_id]);
                }
            }

            DB::table('patient_files')->whereIn('id', $extraIds)->delete();
        }

        Schema::table('patient_files', function (Blueprint $table) {
            $table->unique('patient_id', 'patient_files_patient_id_unique');
        });

        // Normalize legacy visibility values if present.
        if (Schema::hasColumn('medical_visits', 'visibility')) {
            DB::table('medical_visits')->where('visibility', 'all_doctors')->update(['visibility' => 'public']);
            DB::table('medical_visits')->where('visibility', 'restricted')->update(['visibility' => 'approved_doctors']);

            // Rebuild enum safely for MySQL.
            DB::statement("ALTER TABLE medical_visits MODIFY COLUMN visibility ENUM('public','approved_doctors','private') NOT NULL DEFAULT 'approved_doctors'");
        }
    }

    public function down(): void
    {
        Schema::table('patient_files', function (Blueprint $table) {
            $table->dropUnique('patient_files_patient_id_unique');
        });

        if (Schema::hasColumn('medical_visits', 'visibility')) {
            DB::statement("ALTER TABLE medical_visits MODIFY COLUMN visibility ENUM('all_doctors','restricted','public','approved_doctors','private') NOT NULL DEFAULT 'approved_doctors'");
        }
    }
};
