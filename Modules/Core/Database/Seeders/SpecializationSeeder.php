<?php

namespace Modules\Core\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Core\Models\specializations;

class SpecializationSeeder extends Seeder
{
    public function run(): void
    {
        $names = [
            'Cardiology',
            'Dermatology',
            'Neurology',
            'Pediatrics',
            'Orthopedics',
            'Dentistry',
            'Ophthalmology',
        ];

        foreach ($names as $name) {
            $existing = specializations::query()
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
                ->first();

            if ($existing) {
                continue;
            }

            specializations::create(['name' => $name]);
        }
    }
}
