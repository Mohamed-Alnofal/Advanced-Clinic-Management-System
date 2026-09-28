<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Modules\Core\Database\Seeders\GovernorateSeeder;
use Modules\Core\Database\Seeders\SpecializationSeeder;
use Modules\Drug\database\seeders\DrugQuantitySeeder;
use Modules\Drug\database\seeders\PharmacistDrugSeeder;
use Modules\Laboratory\database\seeders\LaboratoryDatabaseSeeder;
use Modules\Users\Database\Seeders\UsersSeeder;
use Modules\MedicalRecords\Database\Seeders\MedicalReferenceRangeSeeder;
class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
public function run(): void
{
    $this->call([
        // GovernorateSeeder::class,
        // RoleSeeder::class,
        // SpecializationSeeder::class,
        // UsersSeeder::class,
       // GovernorateSeeder::class,
       // RoleSeeder::class,
       // SpecializationSeeder::class,
        //UsersSeeder::class,
        // PharmacistDrugSeeder::class,
        // TransferClinicDataSeeder::class,
        // DrugQuantitySeeder::class,
        // MedicalReferenceRangeSeeder::class,
        LaboratoryDatabaseSeeder::class,
        HomeCareSpecialtySeeder::class, 
    ]);
}
}
