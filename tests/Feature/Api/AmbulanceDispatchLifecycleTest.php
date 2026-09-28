<?php



namespace Tests\Feature\Api;



use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

use Modules\Ambulance\Models\AmbulanceCompany;

use Modules\Ambulance\Models\AmbulanceDriver;

use Modules\Ambulance\Models\AmbulanceUnit;

use Modules\Ambulance\Models\EmergencyRequest;

use Modules\Ambulance\Models\EmergencyRequestOffer;

use Modules\Ambulance\Services\AmbulanceDispatchService;

use Modules\Ambulance\Services\NearestAmbulanceService;

use Spatie\Permission\Models\Role;

use Tests\TestCase;



class AmbulanceDispatchLifecycleTest extends TestCase

{

    private function ensureAmbulanceRole(): void

    {

        Role::firstOrCreate(['name' => 'ambulance', 'guard_name' => 'api']);

    }



    private function authenticateAsDriver(AmbulanceDriver $driver): static
    {
        $token = auth('ambulance-driver')->login($driver);

        return $this->withHeader('Authorization', 'Bearer '.$token);
    }



    private function createCompanyWithFleet(array $near = [], bool $withFar = true): array

    {

        $this->ensureAmbulanceRole();

        $owner = $this->createUserWithRole('ambulance');

        $gov = $this->createGovernorate('Damascus');



        $company = AmbulanceCompany::create([

            'user_id' => $owner->id,

            'governorate_id' => $gov->id,

            'name' => 'Test Amb Co',

            'license_number' => 'LIC-'.uniqid(),

            'phone' => $this->uniquePhone(),

            'email' => $this->uniqueEmail('ambco'),

            'address' => 'Damascus',

            'latitude' => 33.51,

            'longitude' => 36.27,

            'status' => 'approved',

            'is_active' => true,

        ]);



        $nearDriver = AmbulanceDriver::create([

            'ambulance_company_id' => $company->id,

            'name' => 'Near Driver',

            'phone' => $this->uniquePhone(),

            'email' => $this->uniqueEmail('neardrv'),

            'password' => Hash::make('password'),

            'is_active' => true,

        ]);



        $nearUnit = AmbulanceUnit::create(array_merge([

            'ambulance_company_id' => $company->id,

            'ambulance_driver_id' => $nearDriver->id,

            'plate_number' => 'NEAR-'.uniqid(),

            'status' => 'available',

            'latitude' => 33.5140,

            'longitude' => 36.2768,

            'is_active' => true,

        ], $near));



        $farDriver = null;

        $farUnit = null;

        if ($withFar) {

            $farDriver = AmbulanceDriver::create([

                'ambulance_company_id' => $company->id,

                'name' => 'Far Driver',

                'phone' => $this->uniquePhone(),

                'email' => $this->uniqueEmail('fardrv'),

                'password' => Hash::make('password'),

                'is_active' => true,

            ]);

            $farUnit = AmbulanceUnit::create([

                'ambulance_company_id' => $company->id,

                'ambulance_driver_id' => $farDriver->id,

                'plate_number' => 'FAR-'.uniqid(),

                'status' => 'available',

                'latitude' => 33.5600,

                'longitude' => 36.3600,

                'is_active' => true,

            ]);

        }



        return compact('owner', 'company', 'nearDriver', 'nearUnit', 'farDriver', 'farUnit');

    }



    public function test_nearest_service_picks_closest_available_unit(): void

    {

        $fleet = $this->createCompanyWithFleet();



        /** @var NearestAmbulanceService $svc */

        $svc = app(NearestAmbulanceService::class);

        $found = $svc->findNearest(33.5138, 36.2765);



        $this->assertNotNull($found);

        $this->assertSame($fleet['nearUnit']->id, $found->id);

    }



    public function test_sos_creates_pending_offer_without_assign_or_busy(): void

    {

        $fleet = $this->createCompanyWithFleet();

        $patient = $this->createPatient();



        $response = $this->authenticateAs($patient)

            ->postJson('/api/ambulance/sos', [

                'latitude' => 33.5138,

                'longitude' => 36.2765,

                'description' => 'معلومات حساسة يجب أن تبقى سرية',

            ]);



        $response->assertCreated();

        $this->assertTrue((bool) $response->json('dispatch.offered'));

        $this->assertFalse((bool) $response->json('dispatch.assigned'));

        $this->assertNull($response->json('request.ambulance_unit_id'));

        $this->assertSame('pending', $response->json('request.status'));

        $this->assertSame('available', $fleet['nearUnit']->fresh()->status);



        $requestId = (int) $response->json('request.id');
        $rawDescription = DB::table('emergency_requests')->where('id', $requestId)->value('description');

        $this->assertNotSame('معلومات حساسة يجب أن تبقى سرية', $rawDescription);
        $this->assertSame('معلومات حساسة يجب أن تبقى سرية', Crypt::decryptString($rawDescription));

        $this->assertDatabaseHas('emergency_request_offers', [

            'ambulance_unit_id' => $fleet['nearUnit']->id,

            'ambulance_driver_id' => $fleet['nearDriver']->id,

            'status' => 'offered',

        ]);

    }



    public function test_driver_sees_offered_request_and_accept_sets_assigned_busy(): void

    {

        $fleet = $this->createCompanyWithFleet();

        $patient = $this->createPatient();



        $created = $this->authenticateAs($patient)

            ->postJson('/api/ambulance/sos', [

                'latitude' => 33.5138,

                'longitude' => 36.2765,

            ])

            ->assertCreated();



        $requestId = (int) $created->json('request.id');



        $this->authenticateAsDriver($fleet['nearDriver'])

            ->getJson('/api/driver/requests')

            ->assertOk()

            ->assertJsonPath('data.0.id', $requestId)

            ->assertJsonPath('data.0.case_number', $created->json('request.case_number'));



        $this->authenticateAsDriver($fleet['nearDriver'])

            ->postJson("/api/driver/requests/{$requestId}/accept")

            ->assertOk()

            ->assertJsonPath('data.status', 'assigned');



        $this->assertSame('busy', $fleet['nearUnit']->fresh()->status);

        $this->assertNotNull(EmergencyRequest::find($requestId)->accepted_at);

    }



    public function test_driver_reject_offers_next_nearest_unit(): void

    {

        $fleet = $this->createCompanyWithFleet();

        $patient = $this->createPatient();



        $created = $this->authenticateAs($patient)

            ->postJson('/api/ambulance/sos', [

                'latitude' => 33.5138,

                'longitude' => 36.2765,

            ])

            ->assertCreated();



        $requestId = (int) $created->json('request.id');



        $this->authenticateAsDriver($fleet['nearDriver'])

            ->postJson("/api/driver/requests/{$requestId}/reject")

            ->assertOk();



        $this->assertDatabaseHas('emergency_request_offers', [

            'emergency_request_id' => $requestId,

            'ambulance_unit_id' => $fleet['nearUnit']->id,

            'status' => 'rejected',

        ]);



        $this->assertDatabaseHas('emergency_request_offers', [

            'emergency_request_id' => $requestId,

            'ambulance_unit_id' => $fleet['farUnit']->id,

            'status' => 'offered',

        ]);



        $this->assertSame('pending', EmergencyRequest::find($requestId)->status);

        $this->assertSame('available', $fleet['nearUnit']->fresh()->status);

    }



    public function test_driver_accept_is_idempotent_under_lock(): void

    {

        $fleet = $this->createCompanyWithFleet([], false);

        $patient = $this->createPatient();



        $request = EmergencyRequest::create([

            'user_id' => $patient->id,

            'case_number' => 'SOS-TEST-'.uniqid(),

            'latitude' => 33.51,

            'longitude' => 36.27,

            'priority' => 'high',

            'status' => 'pending',

            'requested_at' => now(),

        ]);



        EmergencyRequestOffer::create([

            'emergency_request_id' => $request->id,

            'ambulance_unit_id' => $fleet['nearUnit']->id,

            'ambulance_driver_id' => $fleet['nearDriver']->id,

            'status' => 'offered',

            'offered_at' => now(),

        ]);



        /** @var AmbulanceDispatchService $dispatch */

        $dispatch = app(AmbulanceDispatchService::class);

        $first = $dispatch->acceptByDriver($request, $fleet['nearDriver']->id);

        $second = $dispatch->acceptByDriver($request->fresh(), $fleet['nearDriver']->id);



        $this->assertNotNull($first->accepted_at);

        $this->assertTrue($first->accepted_at->equalTo($second->accepted_at));

        $this->assertSame('busy', $fleet['nearUnit']->fresh()->status);

    }



    public function test_patient_can_cancel_pending_request(): void

    {

        $fleet = $this->createCompanyWithFleet();

        $patient = $this->createPatient();



        $created = $this->authenticateAs($patient)

            ->postJson('/api/ambulance/sos', [

                'latitude' => 33.5138,

                'longitude' => 36.2765,

            ])

            ->assertCreated();



        $id = (int) $created->json('request.id');



        $this->authenticateAs($patient)

            ->postJson("/api/ambulance/request/{$id}/cancel", ['reason' => 'false alarm'])

            ->assertOk()

            ->assertJsonPath('data.status', 'cancelled');



        $this->assertSame('available', $fleet['nearUnit']->fresh()->status);

        $this->assertDatabaseHas('emergency_request_offers', [

            'emergency_request_id' => $id,

            'status' => 'expired',

        ]);

    }



    public function test_patient_cannot_cancel_after_driver_accepts(): void

    {

        $fleet = $this->createCompanyWithFleet([], false);

        $patient = $this->createPatient();



        $created = $this->authenticateAs($patient)

            ->postJson('/api/ambulance/sos', [

                'latitude' => 33.5138,

                'longitude' => 36.2765,

            ])

            ->assertCreated();



        $id = (int) $created->json('request.id');



        $this->authenticateAsDriver($fleet['nearDriver'])

            ->postJson("/api/driver/requests/{$id}/accept")

            ->assertOk();



        $this->authenticateAs($patient)

            ->postJson("/api/ambulance/request/{$id}/cancel", ['reason' => 'changed mind'])

            ->assertStatus(422);

    }



    public function test_my_request_returns_active_status_for_patient(): void

    {

        $fleet = $this->createCompanyWithFleet([], false);

        $patient = $this->createPatient();



        $this->authenticateAs($patient)

            ->postJson('/api/ambulance/sos', [

                'latitude' => 33.5138,

                'longitude' => 36.2765,

            ])

            ->assertCreated();



        $this->authenticateAs($patient)

            ->getJson('/api/ambulance/my-request')

            ->assertOk()

            ->assertJsonPath('data.status', 'pending')

            ->assertJsonPath('data.assigned_ambulance', null);



        $requestId = (int) EmergencyRequest::where('user_id', $patient->id)->value('id');



        $this->authenticateAsDriver($fleet['nearDriver'])

            ->postJson("/api/driver/requests/{$requestId}/accept")

            ->assertOk();



        $this->authenticateAs($patient)

            ->getJson('/api/ambulance/my-request')

            ->assertOk()

            ->assertJsonPath('data.status', 'assigned')

            ->assertJsonPath('data.driver.name', 'Near Driver');

    }

}

