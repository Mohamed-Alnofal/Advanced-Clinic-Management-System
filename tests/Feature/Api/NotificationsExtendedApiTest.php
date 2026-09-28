<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

class NotificationsExtendedApiTest extends TestCase
{
    public function test_authenticated_user_can_fetch_unread_notifications(): void
    {
        $patient = $this->createPatient();

        $this->authenticateAs($patient)
            ->getJson('/api/notifications/unread')
            ->assertOk();
    }

    public function test_authenticated_user_can_fetch_notification_count(): void
    {
        $patient = $this->createPatient();

        $this->authenticateAs($patient)
            ->getJson('/api/notifications/count')
            ->assertOk();
    }

    public function test_notification_count_requires_authentication(): void
    {
        $this->getJson('/api/notifications/count')->assertUnauthorized();
    }
}
