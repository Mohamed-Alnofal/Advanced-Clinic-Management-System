<?php

namespace Modules\Subscription\database\seeders;

use Illuminate\Database\Seeder;

class SubscriptionDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call([
            SubscriptionPlanSeeder::class,
        ]);
    }
}