<?php

namespace Tests\Concerns;

use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Modules\Appointments\Models\Appointment;
use Modules\Clinics\Models\Clinic;
use Modules\Core\Models\Governorate;
use Modules\Core\Models\specializations;
use Modules\Doctors\Models\Doctor;
use Modules\Doctors\Models\DoctorSchedule;
use Modules\Drug\Models\Drug;
use Modules\Drug\Models\Price;
use Modules\MedicalFacility\Models\MedicalFacility;
use Modules\Patients\Models\PatientFile;
use Modules\Patients\Models\Patients;
use Modules\Pharmacist\Models\Pharmacist;
use Modules\Pharmacy\Models\Pharmacy;
use Modules\Subscription\Models\SubscriptionPlan;
use Modules\Users\Models\User;
use Modules\Wallet\Models\Wallet;
use Modules\WalletTransaction\Models\WalletTransaction;
use Spatie\Permission\Models\Role;
use Tymon\JWTAuth\Facades\JWTAuth;

trait AcmsTestHelpers
{
    protected function seedAcmsRoles(): void
    {
        foreach (['admin', 'doctor', 'pharmacist', 'user', 'provider'] as $roleName) {
            Role::firstOrCreate([
                'name' => $roleName,
                'guard_name' => 'api',
            ]);
        }

        $this->ensureAdminWallet();
    }

    protected function ensureAdminWallet(): void
    {
        $admin = User::role('admin')->first() ?? $this->createAdmin();

        Wallet::firstOrCreate(
            ['user_id' => $admin->id],
            ['balance' => 0],
        );
    }

    protected function giveUserProviderRole(User $user): void
    {
        Role::firstOrCreate([
            'name' => 'provider',
            'guard_name' => 'api',
        ]);

        if (! $user->hasRole('provider')) {
            $user->assignRole('provider');
        }
    }

    protected function authenticateAs(User $user): static
    {
        $this->app['auth']->forgetGuards();
        $token = JWTAuth::fromUser($user);
        JWTAuth::setToken($token);
        auth('api')->setUser($user);

        return $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->withHeader('Accept', 'application/json');
    }

    /**
     * Assert the JSON `message` without coupling to punctuation or extra copy.
     * Contract tests should prove the outcome (status + DB), not marketing wording.
     */
    protected function assertJsonMessageContains(TestResponse $response, string $expected): void
    {
        $actual = trim((string) $response->json('message'));
        $this->assertNotSame('', $actual, 'API response is missing `message`.');

        $normalize = static function (string $value): string {
            $collapsed = preg_replace('/\s+/', ' ', $value) ?? $value;

            return mb_strtolower(rtrim($collapsed, ". \t\n\r"));
        };

        $this->assertStringContainsString(
            $normalize($expected),
            $normalize($actual),
            "Unexpected API message [{$actual}], expected to contain [{$expected}]."
        );
    }

    protected function uniqueEmail(string $prefix = 'user'): string
    {
        return $prefix.'_'.uniqid().'@acms.test';
    }

    protected function uniquePhone(): string
    {
        return '09'.str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT);
    }

    protected function createUserWithRole(string $role, array $attributes = []): User
    {
        $user = User::create(array_merge([
            'name' => ucfirst($role).' User',
            'email' => $this->uniqueEmail($role),
            'phone' => $this->uniquePhone(),
            'password' => Hash::make('password'),
            'is_verified' => true,
            'status' => 'active',
        ], $attributes));

        $user->assignRole($role);

        return $user;
    }

    protected function createPatient(array $attributes = []): User
    {
        return $this->createUserWithRole('user', $attributes);
    }

    protected function createAdmin(array $attributes = []): User
    {
        return $this->createUserWithRole('admin', $attributes);
    }

    protected function createGovernorate(string $name = 'Damascus'): Governorate
    {
        return Governorate::create(['name' => $name]);
    }

    protected function createSpecialization(string $name = 'Cardiology'): specializations
    {
        return specializations::create(['name' => $name]);
    }

    protected function createDoctorUser(array $userAttributes = [], array $doctorAttributes = []): array
    {
        $governorate = $this->createGovernorate();
        $specialization = $this->createSpecialization();

        $doctorUser = $this->createUserWithRole('doctor', $userAttributes);

        $doctor = Doctor::create(array_merge([
            'user_id' => $doctorUser->id,
            'specialization_id' => $specialization->id,
            'bio' => 'Experienced physician',
            'experience_years' => 8,
            'is_verified' => true,
        ], $doctorAttributes));

        return compact('governorate', 'specialization', 'doctorUser', 'doctor');
    }

    protected function giveDoctorActiveSubscription(Doctor $doctor, array $planAttributes = []): void
    {
        $plan = SubscriptionPlan::create(array_merge([
            'name' => 'Doctor Monthly',
            'subscriber_type' => 'doctor',
            'duration' => 'monthly',
            'price' => 20,
            'is_active' => true,
        ], $planAttributes));

        $doctor->subscriptions()->create([
            'subscription_plan_id' => $plan->id,
            'starts_at' => now()->subMinute(),
            'ends_at' => now()->addMonth(),
            'status' => 'active',
            'auto_renew' => false,
            'cancel_at_period_end' => false,
        ]);
    }

    protected function createPharmacistUser(array $userAttributes = [], array $pharmacistAttributes = []): array
    {
        $pharmacistUser = $this->createUserWithRole('pharmacist', $userAttributes);

        $pharmacist = Pharmacist::create(array_merge([
            'user_id' => $pharmacistUser->id,
            'license_number' => 'PH-'.uniqid(),
            'is_verified' => true,
        ], $pharmacistAttributes));

        return compact('pharmacistUser', 'pharmacist');
    }

    protected function createDoctorWithClinicAndSchedule(array $overrides = []): array
    {
        $setup = $this->createDoctorUser(
            $overrides['user'] ?? [],
            $overrides['doctor'] ?? []
        );

        $facility = MedicalFacility::create([
            'governorate_id' => $setup['governorate']->id,
            'name' => 'ACMS Test Clinic',
            'type' => 'clinic',
            'address' => 'Damascus',
            'is_verified' => true,
            'is_active' => true,
        ]);

        $clinic = Clinic::create([
            'medical_facility_id' => $facility->id,
            'doctor_id' => $setup['doctor']->id,
        ]);

        $schedule = DoctorSchedule::create(array_merge([
            'doctor_id' => $setup['doctor']->id,
            'clinic_id' => $clinic->id,
            'day' => 'monday',
            'start_time' => '10:00:00',
            'end_time' => '11:00:00',
            'consultation_duration' => 30,
            'price' => 50,
            'consultation_type' => 'clinic',
            'is_booked' => false,
        ], $overrides['schedule'] ?? []));

        return array_merge($setup, compact('facility', 'clinic', 'schedule'));
    }

    protected function givePharmacistActiveSubscription(Pharmacist $pharmacist, array $planAttributes = []): void
    {
        $plan = SubscriptionPlan::create(array_merge([
            'name' => 'Pharmacy Monthly',
            'subscriber_type' => 'pharmacist',
            'duration' => 'monthly',
            'price' => 15,
            'is_active' => true,
        ], $planAttributes));

        $pharmacist->subscriptions()->create([
            'subscription_plan_id' => $plan->id,
            'starts_at' => now()->subMinute(),
            'ends_at' => now()->addMonth(),
            'status' => 'active',
            'auto_renew' => false,
            'cancel_at_period_end' => false,
        ]);
    }

    protected function createPharmacistWithPharmacy(array $overrides = []): array
    {
        $setup = $this->createPharmacistUser(
            $overrides['user'] ?? [],
            $overrides['pharmacist'] ?? []
        );

        $governorate = $this->createGovernorate($overrides['governorate'] ?? 'Damascus');

        $facility = MedicalFacility::create([
            'governorate_id' => $governorate->id,
            'name' => 'ACMS Test Pharmacy',
            'type' => 'pharmacy',
            'address' => 'Damascus',
            'latitude' => 33.5138,
            'longitude' => 36.2765,
            'is_verified' => true,
            'is_active' => true,
        ]);

        $pharmacy = Pharmacy::create([
            'pharmacist_id' => $setup['pharmacist']->id,
            'medical_facility_id' => $facility->id,
        ]);

        $this->givePharmacistActiveSubscription($setup['pharmacist']);

        return array_merge($setup, compact('governorate', 'facility', 'pharmacy'));
    }

    protected function createWalletForUser(User $user, float $balance = 0): Wallet
    {
        $wallet = Wallet::firstOrCreate(
            ['user_id' => $user->id],
            ['balance' => 0],
        );

        $wallet->forceFill(['balance' => $balance])->save();

        return $wallet->fresh();
    }

    protected function createWalletTransaction(Wallet $wallet, float $amount, string $type = 'deposit', string $status = 'success'): WalletTransaction
    {
        return WalletTransaction::create([
            'wallet_id' => $wallet->id,
            'amount' => $amount,
            'type' => $type,
            'status' => $status,
        ]);
    }

    protected function createDrugForPharmacy($pharmacy, array $attributes = [])
    {
        $otc = (bool) ($attributes['otc'] ?? true);

        $drug = \Modules\Drug\Models\Drug::create([
            'name' => $attributes['name'] ?? 'Test Drug',
            'description' => $attributes['description'] ?? 'Test description',
        ]);

        \Modules\Drug\Models\Product::create([
            'drug_id' => $drug->id,
            'name' => $drug->name,
            'labeller' => 'ACMS Test Lab',
            'dosage_form' => 'tablet',
            'strength' => '500 mg',
            'route' => 'oral',
            'otc' => $otc,
        ]);

        \Modules\Pharmacy\Models\PharmacyDrug::create([
            'pharmacy_id' => $pharmacy->id,
            'drug_id' => $drug->id,
            'price' => $attributes['price'] ?? 10.50,
            'quantity' => $attributes['quantity'] ?? 100,
            'is_available' => true,
        ]);

        return $drug->fresh();
    }

    protected function createPatientRecord(User $user): Patients
    {
        return Patients::create([
            'patientable_type' => User::class,
            'patientable_id' => $user->id,
        ]);
    }

    protected function createPatientFile(Patients $patient, Doctor $doctor, array $attributes = []): PatientFile
    {
        return PatientFile::create(array_merge([
            'patient_id' => $patient->id,
            'created_by' => $doctor->id,
        ], $attributes));
    }

    protected function createAppointmentForPatient($patient, $setup, array $attributes = [])
    {
        $schedule = $setup['schedule'];
        $type = $attributes['appointment_type']
            ?? ($schedule->consultation_type ?? 'clinic');

        return Appointment::create(array_merge([
            'doctor_id' => $setup['doctor']->id,
            'user_id' => $patient->id,
            'clinic_id' => $setup['clinic']->id,
            'schedule_id' => $schedule->id,
            'status' => 'pending',
            'appointment_type' => $type,
            'booking_for' => 'self',
        ], $attributes));
    }

    protected function createUnverifiedUserWithOtp(string $otp = '123456'): User
    {
        return User::create([
            'name' => 'Unverified User',
            'email' => $this->uniqueEmail('unverified'),
            'phone' => $this->uniquePhone(),
            'password' => Hash::make('password'),
            'is_verified' => false,
            'status' => 'active',
            'otp' => $otp,
            'otp_expires_at' => now()->addMinutes(10),
        ]);
    }
}
