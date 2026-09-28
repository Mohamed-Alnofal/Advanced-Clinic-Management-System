<?php

namespace Modules\Laboratory\database\seeders;

use Illuminate\Database\Seeder;
use Modules\Laboratory\Models\Laboratory;
use Modules\Users\Models\User;
use Illuminate\Support\Facades\Hash;

class LaboratoryDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $laboratories = [

            [
                'name' => 'مخبر الشفاء للتحاليل الطبية',
                'email' => 'lab1@gmail.com',
                'phone' => '0551111119',
                'age' => 40,
                'gender' => 'male',

                'license_number' => 'LAB-001',

                'laboratory_phone' => '0552222222',
                'address' => 'الرياض - حي العليا',

                'latitude' => 24.7136,
                'longitude' => 46.6753,
            ],

            [
                'name' => 'مخبر الحياة للتحاليل الطبية',
                'email' => 'lab2@gmail.com',
                'phone' => '0553333333',
                'age' => 38,
                'gender' => 'female',

                'license_number' => 'LAB-002',

                'laboratory_phone' => '0554444444',
                'address' => 'الرياض - حي النخيل',

                'latitude' => 24.7743,
                'longitude' => 46.7386,
            ],

        ];

        foreach ($laboratories as $data) {

            // ======================
            // 👤 LABORATORY USER
            // ======================

            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make('123456'),
                'phone' => $data['phone'],
                'age' => $data['age'],
                'gender' => $data['gender'],
                'is_verified' => true,
            ]);

            // ======================
            // 🔐 ROLE
            // ======================

            $user->assignRole('laboratory');

            // ======================
            // 🧪 LABORATORY
            // ======================

            Laboratory::create([
                'user_id' => $user->id,

                'license_number' => $data['license_number'],

                'name' => $data['name'],

                'image' => 'laboratories/default.jpg',

                'phone' => $data['laboratory_phone'],

                'address' => $data['address'],

                'latitude' => $data['latitude'],

                'longitude' => $data['longitude'],

                'is_verified' => true,

                'is_active' => true,
            ]);
        }
    }
}
