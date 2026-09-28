<?php

namespace Modules\Core\Services;

use Illuminate\Support\Facades\Http;
use Modules\Core\Support\FirebaseCredentials;

/**
 * Legacy HTTP FCM sender (Appointments / test routes).
 * Resolves credentials the same way as Kreait-based services.
 */
class FirebaseService
{
    public function send($token, $title, $body, array $data = [])
    {
        $credentialsPath = FirebaseCredentials::path();
        $projectId = FirebaseCredentials::projectId();

        $client = new \Google\Client();
        $client->setAuthConfig($credentialsPath);
        $client->addScope('https://www.googleapis.com/auth/firebase.messaging');
        $accessToken = $client->fetchAccessTokenWithAssertion()['access_token'] ?? null;

        if (! $accessToken) {
            throw new \RuntimeException('Unable to obtain Firebase access token.');
        }

        $stringData = array_map('strval', $data);

        $response = Http::withToken($accessToken)
            ->post("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send", [
                'message' => [
                    'token' => $token,
                    'notification' => [
                        'title' => $title,
                        'body' => $body,
                    ],
                    'data' => $stringData,
                    'android' => [
                        'priority' => 'high',
                        'notification' => [
                            'channel_id' => 'clinic_high_importance_channel',
                            'sound' => 'default',
                        ],
                    ],
                ],
            ]);

        return $response->json();
    }
}
