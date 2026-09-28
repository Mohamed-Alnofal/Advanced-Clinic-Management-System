<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Modules\Core\Services\FcmService;
use Modules\Notifications\Services\FirebaseService;
use Modules\Notifications\Services\NotificationService;
use Tests\Concerns\AcmsTestHelpers;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;
    use RefreshDatabase;
    use AcmsTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAcmsRoles();
        $this->configureTestingEnvironment();
        $this->fakeExternalServices();
    }

    protected function configureTestingEnvironment(): void
    {
        config([
            'cache.default' => 'array',
            'queue.default' => 'sync',
            'mail.default' => 'array',
        ]);

        Cache::flush();
        Mail::fake();
    }

    protected function fakeExternalServices(): void
    {
        $this->partialMock(NotificationService::class, function ($mock): void {
            $mock->shouldReceive('sendToUser')->zeroOrMoreTimes()->andReturnNull();
        });

        $this->mock(FirebaseService::class, function ($mock): void {
            $mock->shouldReceive('sendToToken')->zeroOrMoreTimes()->andReturnNull();
        });

        $this->mock(FcmService::class, function ($mock): void {
            $mock->shouldReceive('storeToken')->andReturnUsing(function ($userId, $token) {
                return \Modules\Users\Models\FcmToken::updateOrCreate(
                    ['user_id' => $userId, 'token' => $token],
                    []
                );
            });
        });
    }
}
