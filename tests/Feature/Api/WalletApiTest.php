<?php

namespace Tests\Feature\Api;

use Modules\Wallet\Services\WalletTopUpService;
use Tests\TestCase;

class WalletApiTest extends TestCase
{
    public function test_authenticated_user_can_request_wallet_top_up(): void
    {
        $patient = $this->createPatient();
        $this->createWalletForUser($patient);

        $this->mock(WalletTopUpService::class, function ($mock): void {
            $mock->shouldReceive('createCheckout')
                ->once()
                ->andReturn(['checkout_url' => 'https://checkout.stripe.test/cs_test_123']);
        });

        $this->authenticateAs($patient)
            ->postJson('/api/wallet/top-up', ['amount' => 50])
            ->assertOk()
            ->assertJsonPath('message', 'Checkout created successfully')
            ->assertJsonPath('data.checkout_url', 'https://checkout.stripe.test/cs_test_123');
    }

    public function test_top_up_requires_authentication(): void
    {
        $this->postJson('/api/wallet/top-up', ['amount' => 10])
            ->assertUnauthorized();
    }

    public function test_top_up_validates_amount(): void
    {
        $patient = $this->createPatient();

        $this->authenticateAs($patient)
            ->postJson('/api/wallet/top-up', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['amount']);
    }

    public function test_authenticated_user_can_view_wallet_balance(): void
    {
        $patient = $this->createPatient();
        $this->createWalletForUser($patient, 125.50);

        $response = $this->authenticateAs($patient)
            ->getJson('/api/wallet/getBalance')
            ->assertOk();

        $this->assertEqualsWithDelta(125.5, (float) $response->json('balance'), 0.001);
    }

    public function test_get_balance_returns_not_found_without_wallet(): void
    {
        $patient = $this->createPatient();

        $this->authenticateAs($patient)
            ->getJson('/api/wallet/getBalance')
            ->assertNotFound()
            ->assertJsonPath('message', 'Wallet not found');
    }

    public function test_get_balance_requires_authentication(): void
    {
        $this->getJson('/api/wallet/getBalance')->assertUnauthorized();
    }
}
