<?php
// Modules/Users/Database/seeders/UsersSeeder.php

namespace Modules\Users\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Faker\Factory as Faker;

use Modules\Users\Models\User;
use Modules\Doctors\Models\Doctor;
use Modules\Pharmacist\Models\Pharmacist;

// 👇 أضف هذه الثلاثة
use Modules\MedicalFacility\Models\MedicalFacility;
use Modules\Clinics\Models\Clinic;
use Modules\Pharmacy\Models\Pharmacy;

class UsersSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create('ar_SA'); // استخدام العربية للمملكة

        // ======================
        // 👑 ADMIN (واحد فقط)
        // ======================
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@gmail.com',
            'password' => Hash::make('123456'),
            'phone' => '0500000000',
            'age' => 40,
            'gender' => 'male',
            'is_verified' => true,
        ]);
        $admin->assignRole('admin');

        // ======================
        // 👤 USERS (4 مستخدمين عاديين)
        // ======================

        $user = User::create([
            'name' => 'Normal User',
            'email' => 'user@gmail.com',
            'password' => Hash::make('123456'),
            'phone' => '0500000001',
            'age' => 40,
            'gender' => 'male',
            'is_verified' => true,
        ]);
        $user->assignRole('user');

        $usersData = [
            [
                'name' => 'Ahmed Mansour',
                'email' => 'ahmed@gmail.com',
                'phone' => '0511111111',
                'age' => 25,
                'gender' => 'male'
            ],
            [
                'name' => 'Sara Khalid',
                'email' => 'sara@gmail.com',
                'phone' => '0522222222',
                'age' => 28,
                'gender' => 'female'
            ],
            [
                'name' => 'Mohammed Fahad',
                'email' => 'mohammed@gmail.com',
                'phone' => '0533333333',
                'age' => 30,
                'gender' => 'male'
            ],
            [
                'name' => 'Nora Abdullah',
                'email' => 'nora@gmail.com',
                'phone' => '0544444444',
                'age' => 26,
                'gender' => 'female'
            ],
        ];

        foreach ($usersData as $userData) {
            $user = User::create([
                'name' => $userData['name'],
                'email' => $userData['email'],
                'password' => Hash::make('123456'),
                'phone' => $userData['phone'],
                'age' => $userData['age'],
                'gender' => $userData['gender'],
                'is_verified' => true,
            ]);
            $user->assignRole('user');
        }


        // ======================
        // 🧑‍⚕️ DOCTORS (4 أطباء)
        // ======================

        $doctorUser = User::create([
            'name' => 'Dr Ahmed',
            'email' => 'doctor@gmail.com',
            'password' => Hash::make('123456'),
            'phone' => '0500000002',
            'age' => 40,
            'gender' => 'male',
            'is_verified' => true,
        ]);
       // ======================
        // 🧑‍⚕️ DOCTORS
        // ======================
        $doctors = [
            [
                'name' => 'Dr Ahmed',
                'email' => 'dr.ahmed@gmail.com',
                'phone' => '0551111111',
                'age' => 45,
                'gender' => 'male',
                'specialization_id' => 1,
                'bio' => 'استشاري قلب',
                'experience_years' => 15,
                'facility_name' => 'عيادة الدكتور أحمد',
                'latitude' => 24.7136,
                'longitude' => 46.6753,
                'address' => 'الرياض - طريق الملك فهد',
            ],
        ];


        foreach ($doctors as $data) {

            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make('123456'),
                'phone' => $data['phone'],
                'age' => $data['age'],
                'gender' => $data['gender'],
                'is_verified' => true,
            ]);

            $user->assignRole('doctor');

            $doctor = Doctor::create([
                'user_id' => $user->id,
                'specialization_id' => $data['specialization_id'],
                'bio' => $data['bio'],
                'experience_years' => $data['experience_years'],
                'image' => 'doctors/default.jpg',
                'is_verified' => true,
            ]);

            $facility = MedicalFacility::create([
                'governorate_id' => 1,
                'name' => $data['facility_name'],
                'type' => 'clinic',
                'latitude' => $data['latitude'],
                'longitude' => $data['longitude'],
                'address' => $data['address'],
                'phone' => $data['phone'],
                'is_verified' => true,
                'is_active' => true,
            ]);

            Clinic::create([
                'doctor_id' => $doctor->id,
                'medical_facility_id' => $facility->id,
            ]);
        }

        // ======================
        // 💊 PHARMACISTS
        // ======================

        $pharmaUser = User::create([
            'name' => 'Pharmacist Ali',
            'email' => 'pharmacist@gmail.com',
            'password' => Hash::make('123456'),
            'phone' => '0550000001',
            'age' => 35,
            'gender' => 'male',
            // 'is_verified' => true,

        ]);
        $pharmacists = [
            [
                'name' => 'Ali Hassan',
                'email' => 'ali@gmail.com',
                'phone' => '0561111111',
                'age' => 35,
                'gender' => 'male',
                'license_number' => 'LIC-001',
                'facility_name' => 'صيدلية النور',
                'latitude' => 24.7136,
                'longitude' => 46.6753,
                'address' => 'الرياض - حي العليا',
            ],
        ];

        foreach ($pharmacists as $data) {

            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make('123456'),
                'phone' => $data['phone'],
                'age' => $data['age'],
                'gender' => $data['gender'],
                'is_verified' => true,
            ]);

            $user->assignRole('pharmacist');

            $pharmacist = Pharmacist::create([
                'user_id' => $user->id,
                'license_number' => $data['license_number'],
                'image' => 'pharmacists/default.jpg',
                'is_verified' => true,
                // 'is_active' => true,
            ]);

            $facility = MedicalFacility::create([
                'governorate_id' => 1,
                'name' => $data['facility_name'],
                'type' => 'pharmacy',
                'latitude' => $data['latitude'],
                'longitude' => $data['longitude'],
                'address' => $data['address'],
                'phone' => $data['phone'],
                'is_verified' => true,
                'is_active' => true,
            ]);

            Pharmacy::create([
                'pharmacist_id' => $pharmacist->id,
                'medical_facility_id' => $facility->id,
            ]);
        }
    }
}