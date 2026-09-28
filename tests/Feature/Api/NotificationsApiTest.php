<?php

namespace Tests\Feature\Api;

use Modules\Notifications\Models\Notifications;
use Tests\TestCase;

class NotificationsApiTest extends TestCase
{
    public function test_authenticated_user_can_list_notifications(): void
    {
        $patient = $this->createPatient();

        Notifications::create([
            'type' => 'appointment',
            'notifiable_id' => $patient->id,
            'notifiable_type' => get_class($patient),
            'data' => [
                'title' => 'Appointment Reminder',
                'body' => 'You have an upcoming appointment',
            ],
        ]);

        $this->authenticateAs($patient)
            ->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonFragment(['title' => 'Appointment Reminder']);
    }

    public function test_notifications_requires_authentication(): void
    {
        $this->getJson('/api/notifications')->assertUnauthorized();
    }

    public function test_notifications_returns_empty_list_for_new_user(): void
    {
        $patient = $this->createPatient();

        $this->authenticateAs($patient)
            ->getJson('/api/notifications')
            ->assertOk()
            ->assertJson([]);
    }
}
