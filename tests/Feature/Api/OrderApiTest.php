<?php

namespace Tests\Feature\Api;

use Modules\Order\Models\Order;
use Tests\TestCase;

class OrderApiTest extends TestCase
{
    public function test_patient_can_create_order(): void
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
                    'quantity' => 2,
                ]],
            ])
            ->assertSuccessful()
            ->assertJsonStructure(['data']);

        $this->assertDatabaseHas('orders', [
            'user_id' => $patient->id,
            'pharmacy_id' => $pharmacySetup['pharmacy']->id,
            'status' => 'pending',
        ]);
    }

    public function test_create_order_requires_authentication(): void
    {
        $this->postJson('/api/orders', [])->assertUnauthorized();
    }

    public function test_create_order_validates_required_fields(): void
    {
        $patient = $this->createPatient();

        $this->authenticateAs($patient)
            ->postJson('/api/orders', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['pharmacy_id', 'items']);
    }

    public function test_patient_can_list_own_orders(): void
    {
        $patient = $this->createPatient();
        $pharmacySetup = $this->createPharmacistWithPharmacy();

        Order::create([
            'user_id' => $patient->id,
            'pharmacy_id' => $pharmacySetup['pharmacy']->id,
            'pharmacist_id' => $pharmacySetup['pharmacist']->id,
            'status' => 'pending',
            'payment_status' => 'cash_on_delivery',
            'total_price' => 25,
        ]);

        $this->authenticateAs($patient)
            ->getJson('/api/orders')
            ->assertOk();
    }
}
