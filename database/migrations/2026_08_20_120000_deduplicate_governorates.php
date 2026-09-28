<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('governorates')) {
            return;
        }

        $duplicates = DB::table('governorates')
            ->select('name', DB::raw('MIN(id) as keep_id'), DB::raw('COUNT(*) as cnt'))
            ->groupBy('name')
            ->having('cnt', '>', 1)
            ->get();

        $fkTables = ['medical_facilities', 'doctors', 'pharmacies', 'ambulance_companies'];

        foreach ($duplicates as $row) {
            $keepId = (int) $row->keep_id;
            $dropIds = DB::table('governorates')
                ->where('name', $row->name)
                ->where('id', '!=', $keepId)
                ->pluck('id');

            foreach ($dropIds as $oldId) {
                foreach ($fkTables as $table) {
                    if (Schema::hasTable($table) && Schema::hasColumn($table, 'governorate_id')) {
                        DB::table($table)
                            ->where('governorate_id', $oldId)
                            ->update(['governorate_id' => $keepId]);
                    }
                }

                DB::table('governorates')->where('id', $oldId)->delete();
            }
        }
    }

    public function down(): void
    {
        // Irreversible data cleanup.
    }
};
