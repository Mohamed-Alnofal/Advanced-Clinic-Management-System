<?php

namespace Modules\Auth\Database\seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        // Role::firstOrCreate([
        //     'name' => 'admin',
        //     'guard_name' => 'api',
        // ]);

        // Role::firstOrCreate([
        //     'name' => 'doctor',
        //     'guard_name' => 'api',
        // ]);

        // Role::firstOrCreate([
        //     'name' => 'pharmacist',
        //     'guard_name' => 'api',
        // ]);

        // Role::firstOrCreate([
        //     'name' => 'user',
        //     'guard_name' => 'api',
        // ]);

        // Role::firstOrCreate([
        //     'name' => 'ambulance',
        //     'guard_name' => 'api',
        // ]);

        Role::firstOrCreate([
            'name' => 'laboratory',
            'guard_name' => 'api',
        ]);

        Role::firstOrCreate([
            'name' => 'provider',
            'guard_name' => 'api',
        ]);

        Role::firstOrCreate([
            'name' => 'ambulance',
            'guard_name' => 'api',
        ]);
    }
}