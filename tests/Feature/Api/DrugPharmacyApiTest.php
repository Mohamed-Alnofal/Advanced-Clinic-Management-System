<?php

namespace Tests\Feature\Api;

use Modules\Drug\Models\Drug;
use Tests\TestCase;

class DrugPharmacyApiTest extends TestCase
{
    public function test_pharmacist_can_create_drug(): void
    {
        $setup = $this->createPharmacistWithPharmacy();

        $this->authenticateAs($setup['pharmacistUser'])
            ->postJson('/api/drugs', [
                'pharmacy_id' => $setup['pharmacy']->id,
                'name' => 'Amoxicillin',
                'price' => 15.5,
                'quantity' => 20,
            ])
            ->assertCreated()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('drugs', ['name' => 'Amoxicillin']);
    }

    public function test_pharmacist_can_list_drugs(): void
    {
        $setup = $this->createPharmacistWithPharmacy();
        $this->createDrugForPharmacy($setup['pharmacy']);

        $this->authenticateAs($setup['pharmacistUser'])
            ->getJson('/api/drugs')
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_pharmacist_can_delete_drug(): void
    {
        $setup = $this->createPharmacistWithPharmacy();
        $drug = $this->createDrugForPharmacy($setup['pharmacy']);

        $this->authenticateAs($setup['pharmacistUser'])
            ->deleteJson('/api/drugs/'.$drug->id)
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('pharmacy_drug', [
            'drug_id' => $drug->id,
            'pharmacy_id' => $setup['pharmacy']->id,
        ]);
    }

    public function test_drug_crud_requires_pharmacist_role(): void
    {
        $patient = $this->createPatient();

        $this->authenticateAs($patient)
            ->getJson('/api/drugs')
            ->assertForbidden();
    }

    public function test_drug_store_validates_required_fields(): void
    {
        $setup = $this->createPharmacistWithPharmacy();

        $this->authenticateAs($setup['pharmacistUser'])
            ->postJson('/api/drugs', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['pharmacy_id', 'name', 'price', 'quantity']);
    }

    public function test_pharmacist_can_create_pharmacy(): void
    {
        $setup = $this->createPharmacistUser();

        $this->authenticateAs($setup['pharmacistUser'])
            ->postJson('/api/pharmacies', [
                'governorate_id' => $this->createGovernorate()->id,
                'name' => 'City Pharmacy',
                'latitude' => 33.5138,
                'longitude' => 36.2765,
                'address' => 'Damascus',
            ])
            ->assertCreated()
            ->assertJsonPath('message', 'Pharmacy created successfully and verified.');
    }

    public function test_pharmacy_store_requires_authentication(): void
    {
        $this->postJson('/api/pharmacies', [])->assertUnauthorized();
    }
}
