<?php

namespace Tests\Feature\Api;

use Modules\HomeCare\Models\HomeCareProfile;
use Modules\HomeCare\Models\HomeCareSpecialty;
use Tests\TestCase;

class HomeCareApiTest extends TestCase
{
    private function seedSpecialty(): HomeCareSpecialty
    {
        return HomeCareSpecialty::create([
            'name' => 'Home nursing',
            'slug' => 'nursing-'.uniqid(),
            'description' => 'Nursing at home',
            'is_active' => true,
        ]);
    }

    public function test_patient_can_apply_and_admin_can_approve_without_losing_user_role(): void
    {
        $patient = $this->createPatient();
        $specialty = $this->seedSpecialty();

        $this->authenticateAs($patient)
            ->postJson('/api/home-care/profile', [
                'specialty_id' => $specialty->id,
                'provider_type' => 'nurse',
                'full_name' => 'Ahmad Nurse',
                'license_number' => 'NUR-1',
                'bio' => 'Home nurse',
                'experience_years' => 5,
                'visit_fee' => 20,
                'service_radius_km' => 10,
            ])
            ->assertCreated()
            ->assertJsonPath('data.profile.status', 'pending');

        $profile = HomeCareProfile::where('user_id', $patient->id)->firstOrFail();

        $this->authenticateAs($this->createAdmin())
            ->putJson('/api/admin/home-care/approve/'.$profile->id, [
                'notes' => 'Approved',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');

        $patient->refresh();
        $this->assertTrue($patient->hasRole('user'));
        $this->assertTrue($patient->hasRole('provider'));
    }

    public function test_unapproved_provider_is_hidden_from_patient_catalog(): void
    {
        $patient = $this->createPatient();
        $specialty = $this->seedSpecialty();
        $volunteer = $this->createPatient();

        HomeCareProfile::create([
            'user_id' => $volunteer->id,
            'specialty_id' => $specialty->id,
            'provider_type' => 'nurse',
            'full_name' => 'Hidden Nurse',
            'status' => 'pending',
            'visit_fee' => 15,
        ]);

        $this->authenticateAs($patient)
            ->getJson('/api/home-care')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_patient_can_book_approved_provider_and_provider_sees_own_request(): void
    {
        $patient = $this->createPatient();
        $volunteer = $this->createPatient();
        $specialty = $this->seedSpecialty();

        $profile = HomeCareProfile::create([
            'user_id' => $volunteer->id,
            'specialty_id' => $specialty->id,
            'provider_type' => 'nurse',
            'full_name' => 'Visible Nurse',
            'status' => 'approved',
            'visit_fee' => 25,
            'approved_at' => now(),
            'latitude' => 33.5138,
            'longitude' => 36.2765,
            'service_radius_km' => 50,
        ]);
        $this->giveUserProviderRole($volunteer);

        $this->authenticateAs($patient)
            ->postJson('/api/home-care/requests', [
                'home_care_profile_id' => $profile->id,
                'specialty_id' => $specialty->id,
                'requested_date' => now()->addDay()->toDateString(),
                'requested_time' => '10:00',
                'latitude' => 33.5140,
                'longitude' => 36.2767,
                'address' => 'Damascus',
                'notes' => 'Need a visit',
            ])
            ->assertCreated();

        $this->authenticateAs($volunteer)
            ->getJson('/api/home-care/requests/provider')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->authenticateAs($patient)
            ->getJson('/api/home-care/my-requests')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_approved_caregiver_cannot_request_a_home_visit(): void
    {
        $caregiver = $this->createPatient();
        $other = $this->createPatient();
        $specialty = $this->seedSpecialty();

        HomeCareProfile::create([
            'user_id' => $caregiver->id,
            'specialty_id' => $specialty->id,
            'provider_type' => 'nurse',
            'full_name' => 'Caregiver',
            'status' => 'approved',
            'visit_fee' => 25,
            'approved_at' => now(),
        ]);

        $otherProfile = HomeCareProfile::create([
            'user_id' => $other->id,
            'specialty_id' => $specialty->id,
            'provider_type' => 'nurse',
            'full_name' => 'Other Nurse',
            'status' => 'approved',
            'visit_fee' => 20,
            'approved_at' => now(),
        ]);

        $this->authenticateAs($caregiver)
            ->postJson('/api/home-care/requests', [
                'home_care_profile_id' => $otherProfile->id,
                'requested_date' => now()->addDay()->toDateString(),
                'requested_time' => '10:00',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['home_care']);
    }

    public function test_my_profile_does_not_auto_create_an_application(): void
    {
        $patient = $this->createPatient();

        $this->authenticateAs($patient)
            ->getJson('/api/home-care/my-profile')
            ->assertOk()
            ->assertJsonPath('data', null);

        $this->assertDatabaseMissing('home_care_profiles', [
            'user_id' => $patient->id,
        ]);
    }
}
