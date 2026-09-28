<?php

namespace Tests\Feature\Api;

use Modules\Order\Models\Order;
use Tests\TestCase;

class OrderPharmacistApiTest extends TestCase
{
    public function test_pharmacist_can_list_pending_orders(): void
    {
        $patient = $this->createPatient();
        $pharmacySetup = $this->createPharmacistWithPharmacy();

        Order::create([
            'user_id' => $patient->id,
            'pharmacy_id' => $pharmacySetup['pharmacy']->id,
            'pharmacist_id' => $pharmacySetup['pharmacist']->id,
            'status' => 'pending',
            'payment_status' => 'cash_on_delivery',
            'total_price' => 40,
        ]);

        $this->authenticateAs($pharmacySetup['pharmacistUser'])
            ->getJson('/api/pharmacist/orders')
            ->assertOk();
    }

    public function test_pharmacist_orders_requires_authentication(): void
    {
        $this->getJson('/api/pharmacist/orders')->assertUnauthorized();
    }

    public function test_patient_cannot_access_pharmacist_orders(): void
    {
        $patient = $this->createPatient();

        $this->authenticateAs($patient)
            ->getJson('/api/pharmacist/orders')
            ->assertForbidden();
    }

    public function test_patient_can_cancel_own_order(): void
    {
        $patient = $this->createPatient();
        $pharmacySetup = $this->createPharmacistWithPharmacy();
        $drug = $this->createDrugForPharmacy($pharmacySetup['pharmacy']);

        $this->authenticateAs($patient)
            ->postJson('/api/orders', [
                'pharmacy_id' => $pharmacySetup['pharmacy']->id,
                'payment_status' => 'cash_on_delivery',
                'items' => [[
                    'drug_id' => $drug->id,
                    'quantity' => 1,
                ]],
            ])
            ->assertSuccessful();

        $orderId = Order::where('user_id', $patient->id)->value('id');

        $this->authenticateAs($patient)
            ->postJson("/api/orders/{$orderId}/cancel")
            ->assertSuccessful();

        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'status' => 'cancelled',
        ]);
    }
}
