<?php

namespace Modules\Subscription\database\seeders;

use Illuminate\Database\Seeder;
use Modules\Subscription\Models\SubscriptionPlan;

class SubscriptionPlanSeeder extends Seeder
{
    public function run(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Doctor
        |--------------------------------------------------------------------------
        */

        SubscriptionPlan::updateOrCreate(
            [
                'subscriber_type' => 'doctor',
                'duration' => 'monthly',
            ],
            [
                'name' => 'Doctor Monthly',
                'price' => 20,
                'is_active' => true,
            ]
        );

        SubscriptionPlan::updateOrCreate(
            [
                'subscriber_type' => 'doctor',
                'duration' => 'yearly',
            ],
            [
                'name' => 'Doctor Yearly',
                'price' => 200,
                'is_active' => true,
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | Pharmacist
        |--------------------------------------------------------------------------
        */

        SubscriptionPlan::updateOrCreate(
            [
                'subscriber_type' => 'pharmacist',
                'duration' => 'monthly',
            ],
            [
                'name' => 'Pharmacist Monthly',
                'price' => 30,
                'is_active' => true,
            ]
        );

        SubscriptionPlan::updateOrCreate(
            [
                'subscriber_type' => 'pharmacist',
                'duration' => 'yearly',
            ],
            [
                'name' => 'Pharmacist Yearly',
                'price' => 300,
                'is_active' => true,
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | Home Care Provider
        |--------------------------------------------------------------------------
        */

        SubscriptionPlan::updateOrCreate(
            [
                'subscriber_type' => 'home_care_provider',
                'duration' => 'monthly',
            ],
            [
                'name' => 'Home Care Provider Monthly',
                'price' => 25,
                'is_active' => true,
            ]
        );

        SubscriptionPlan::updateOrCreate(
            [
                'subscriber_type' => 'home_care_provider',
                'duration' => 'yearly',
            ],
            [
                'name' => 'Home Care Provider Yearly',
                'price' => 250,
                'is_active' => true,
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | Pharmacist Premium
        |--------------------------------------------------------------------------
        */

        SubscriptionPlan::updateOrCreate(
            [
                'subscriber_type' => 'pharmacist',
                'duration' => 'monthly',
                'name' => 'Pharmacist Premium',
            ],
            [
                'price' => 50,
                'is_active' => true,
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | Home Care Premium
        |--------------------------------------------------------------------------
        */

        SubscriptionPlan::updateOrCreate(
            [
                'subscriber_type' => 'home_care_provider',
                'duration' => 'monthly',
                'name' => 'Home Care Provider Premium',
            ],
            [
                'price' => 50,
                'is_active' => true,
            ]
        );
    }
}