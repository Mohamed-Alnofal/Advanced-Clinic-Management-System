<?php

namespace Modules\Drug\database\seeders;


use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\Drug\Models\Drug;
use Modules\Pharmacist\Models\Pharmacist;
use Modules\Pharmacy\Models\Pharmacy;

class PharmacistDrugSeeder extends Seeder
{
    public function run(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        // حذف البيانات القديمة (اختياري)
        DB::table('pharmacy_drug')->truncate();

        $this->attachDrugsToPharmacies();

        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }

    private function attachDrugsToPharmacies(): void
    {
        $pharmacies = Pharmacy::all();

        if ($pharmacies->isEmpty()) {
            $this->command->warn('لا توجد صيدليات في قاعدة البيانات.');
            return;
        }

        Drug::orderBy('id')->chunk(500, function ($drugs) use ($pharmacies) {

            foreach ($drugs as $drug) {

                // اختيار عدد عشوائي من الصيدليات لهذا الدواء
                $selectedPharmacies = $pharmacies->random(
                    rand(1, min(10, $pharmacies->count()))
                );

                foreach ($selectedPharmacies as $pharmacy) {

                    $exists = DB::table('pharmacy_drug')
                        ->where('pharmacy_id', $pharmacy->id)
                        ->where('drug_id', $drug->id)
                        ->exists();

                    if (!$exists) {

                        DB::table('pharmacy_drug')->insert([
                            'pharmacy_id'  => $pharmacy->id,
                            'drug_id'      => $drug->id,

                            // سعر عشوائي بين 5 و 150
                            'price'        => rand(500, 15000) / 100,

                            // كمية عشوائية بين 0 و 200
                            'quantity'     => rand(0, 200),

                            // متوفر بنسبة 80%
                            'is_available' => rand(1, 100) <= 80,

                            'created_at'   => now(),
                            'updated_at'   => now(),
                        ]);
                    }
                }
            }
        });
    }
}