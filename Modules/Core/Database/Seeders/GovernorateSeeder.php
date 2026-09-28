<?php

namespace Modules\Core\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class GovernorateSeeder extends Seeder
{
    public function run(): void
    {
        $names = [
            'Damascus',
            'Rif Dimashq',
            'Aleppo',
            'Homs',
            'Hama',
            'Latakia',
            'Tartus',
            'Idlib',
            'Deir ez-Zor',
            'Al-Hasakah',
            'Raqqa',
            'Daraa',
            'As-Suwayda',
            'Quneitra',
        ];

        foreach ($names as $name) {
            $exists = DB::table('governorates')->where('name', $name)->exists();
            if (! $exists) {
                DB::table('governorates')->insert(['name' => $name]);
            }
        }
    }
}
