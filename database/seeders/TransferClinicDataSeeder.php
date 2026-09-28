<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
class TransferClinicDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
{
    DB::statement('SET FOREIGN_KEY_CHECKS=0;');


        // $this->transferDrugs();
        // $this->transferCategories();
        // $this->transferCategoryDrug();
        // $this->transferSynonyms();
        // $this->transferAffectedOrganisms();
        // $this->transferPrices();
        // $this->transferInteractions();
        // $this->transferDosages();
        // $this->transferOrganisms();
        $this->transferProducts();

    $this->transferProducts();

    DB::statement('SET FOREIGN_KEY_CHECKS=1;');
}

private function transferProducts()
{
    DB::connection('old_mysql')
        ->table('products')
        ->orderBy('id')
        ->chunk(500, function ($products) {

            foreach ($products as $product) {

                DB::table('products')->insert([

                    'id' => $product->id,

                    'name' => $product->name,

                    'labeller' => $product->labeller,

                    'dosage_form' => $product->dosage_form,

                    'strength' => $product->strength,

                    'route' => $product->route,

                    'generic' => filter_var(
                        $product->generic,
                        FILTER_VALIDATE_BOOLEAN
                    ),

                    'otc' => filter_var(
                        $product->otc,
                        FILTER_VALIDATE_BOOLEAN
                    ),

                    'drug_id' => $product->drug_id,

                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });
}

 private function transferDrugs()
    {
        $drugs = DB::connection('old_mysql')
            ->table('drugs')
            ->get();

        foreach ($drugs as $drug) {
            DB::table('drugs')->insert((array) $drug);
        }
    }
 private function transferPrices()
    {
        $drugs = DB::connection('old_mysql')
            ->table('prices')
            ->get();

        foreach ($drugs as $drug) {
            DB::table('prices')->insert((array) $drug);
        }
    }

private function transferCategories()
{
    $data = DB::connection('old_mysql')->table('categories')->get();

    foreach ($data as $row) {
        DB::table('categories')->insert((array) $row);
    }
}
private function transferCategoryDrug()
{
    $data = DB::connection('old_mysql')->table('category_drug')->get();

    foreach ($data as $row) {
        DB::table('category_drug')->insert((array) $row);
    }
}

private function transferSynonyms()
{
    $data = DB::connection('old_mysql')->table('synonyms')->get();

    foreach ($data as $row) {
        DB::table('synonyms')->insert((array) $row);
    }
}



private function transferOrganisms()
{
    DB::connection('old_mysql')
        ->table('affected_organisms')
        ->orderBy('id')
        ->chunk(500, function ($rows) {

            foreach ($rows as $row) {
                DB::table('affected_organisms')->insert((array) $row);
            }

        });
}
private function transferDosages()
{
    DB::connection('old_mysql')
        ->table('dosages')
        ->orderBy('id')
        ->chunk(500, function ($rows) {

            foreach ($rows as $row) {
                DB::table('dosages')->insert((array) $row);
            }

        });
}
private function transferInteractions()
{
    DB::connection('old_mysql')
        ->table('interactions')
        ->orderBy('id')
        ->chunk(500, function ($rows) {

            foreach ($rows as $row) {
                DB::table('interactions')->insert((array) $row);
            }

        });
}
private function transferAffectedOrganisms()
{
    $records = DB::connection('old_mysql')
        ->table('affected_organisms')
        ->get();

    foreach ($records as $record) {

        DB::table('affected_organisms')->insert([
            'id' => $record->id, // مهم إذا تريد الحفاظ على IDs القديمة
            'organism' => $record->organism,
            'drug_id' => $record->drug_id,
            'created_at' => $record->created_at ?? now(),
            'updated_at' => $record->updated_at ?? now(),
        ]);
    }
}
}
