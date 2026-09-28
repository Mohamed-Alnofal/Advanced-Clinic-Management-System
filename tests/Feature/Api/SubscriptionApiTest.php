<?php

namespace Tests\Feature\Api;

use Modules\Subscription\Models\SubscriptionPlan;
use Tests\TestCase;

class SubscriptionApiTest extends TestCase
{
    private function seedDoctorPlans(): array
    {
        $monthly = SubscriptionPlan::create([
            'name' => 'Doctor Monthly',
            'subscriber_type' => 'doctor',
            'duration' => 'monthly',
            'price' => 20,
            'is_active' => true,
        ]);

        $yearly = SubscriptionPlan::create([
            'name' => 'Doctor Yearly',
            'subscriber_type' => 'doctor',
            'duration' => 'yearly',
            'price' => 200,
            'is_active' => true,
        ]);

        SubscriptionPlan::create([
            'name' => 'Pharmacist Monthly',
            'subscriber_type' => 'pharmacist',
            'duration' => 'monthly',
            'price' => 30,
            'is_active' => true,
        ]);

        return compact('monthly', 'yearly');
    }

    public function test_doctor_can_list_own_subscription_plans(): void
    {
        $setup = $this->createDoctorUser();
        $this->seedDoctorPlans();

        $this->authenticateAs($setup['doctorUser'])
            ->getJson('/api/subscription/plans')
            ->assertOk()
            ->assertJsonPath('message', 'Subscription plans.')
            ->assertJsonCount(2, 'data');
    }

    public function test_patient_is_not_eligible_for_subscription_plans(): void
    {
        $patient = $this->createPatient();

        $this->authenticateAs($patient)
            ->getJson('/api/subscription/plans')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['user']);
    }

    public function test_doctor_can_subscribe_from_wallet(): void
    {
        $setup = $this->createDoctorUser();
        $this->createWalletForUser($setup['doctorUser'], 100);
        $plans = $this->seedDoctorPlans();

        $this->authenticateAs($setup['doctorUser'])
            ->postJson('/api/subscription/wallet', [
                'plan_id' => $plans['monthly']->id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.plan.id', $plans['monthly']->id);

        $this->authenticateAs($setup['doctorUser'])
            ->getJson('/api/subscription/current')
            ->assertOk()
            ->assertJsonPath('data.status', 'active');
    }

    public function test_doctor_cannot_subscribe_twice(): void
    {
        $setup = $this->createDoctorUser();
        $this->createWalletForUser($setup['doctorUser'], 500);
        $plans = $this->seedDoctorPlans();

        $this->authenticateAs($setup['doctorUser'])
            ->postJson('/api/subscription/wallet', ['plan_id' => $plans['monthly']->id])
            ->assertCreated();

        $this->authenticateAs($setup['doctorUser'])
            ->postJson('/api/subscription/wallet', ['plan_id' => $plans['yearly']->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['subscription']);
    }

    public function test_admin_can_create_and_disable_plan(): void
    {
        $admin = $this->createAdmin();

        $created = $this->authenticateAs($admin)
            ->postJson('/api/admin/subscriptions/plans', [
                'name' => 'Doctor Plus',
                'subscriber_type' => 'doctor',
                'duration' => 'monthly',
                'price' => 35,
                'is_active' => true,
            ])
            ->assertCreated()
            ->json('data');

        $this->authenticateAs($admin)
            ->patchJson('/api/admin/subscriptions/plans/'.$created['id'].'/status', [
                'is_active' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.is_active', false);
    }
}
