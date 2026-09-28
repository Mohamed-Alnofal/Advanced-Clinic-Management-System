<?php

namespace Modules\Drug\database\seeders;

use Illuminate\Database\Seeder;
use Modules\Drug\Models\Drug;

class DrugQuantitySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Drug::chunk(500, function ($drugs) {

            foreach ($drugs as $drug) {

                $drug->update([
                    'quantity' => rand(0, 500)
                ]);

            }
        });
    }
}
