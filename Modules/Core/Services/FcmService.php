<?php

namespace Modules\Core\Services;

use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\AndroidConfig;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Modules\Core\Support\FirebaseCredentials;
use Modules\Users\Models\FcmToken;

/**
 * Used by /fcm/store and /fcm/test.
 * Token storage must never depend on Firebase credentials being valid.
 */
class FcmService
{
    private $messaging = null;

    public function storeToken($userId, $token, $deviceType = null)
    {
        // One device token belongs to one user at a time.
        FcmToken::where('token', $token)
            ->where('user_id', '!=', $userId)
            ->delete();

        return FcmToken::updateOrCreate(
            [
                'user_id' => $userId,
                'token' => $token,
            ]
        );
    }

    public function sendToUser($userId, $title, $body, $data = [])
    {
        $tokens = FcmToken::where('user_id', $userId)
            ->pluck('token')
            ->toArray();

        if ($tokens === []) {
            Log::warning('FCM skipped: no device tokens for user', [
                'user_id' => $userId,
            ]);

            return true;
        }

        foreach ($tokens as $token) {
            try {
                $stringData = array_map('strval', is_array($data) ? $data : []);

                $message = CloudMessage::withTarget('token', $token)
                    ->withNotification(Notification::create($title, $body))
                    ->withData($stringData)
                    ->withAndroidConfig(AndroidConfig::fromArray([
                        'priority' => 'high',
                        'notification' => [
                            'channel_id' => 'clinic_high_importance_channel',
                            'sound' => 'default',
                        ],
                    ]));

                $response = $this->messaging()->send($message);

                Log::info('FCM sent', [
                    'user_id' => $userId,
                    'response' => $response,
                ]);
            } catch (\Exception $e) {
                $this->handleSendFailure($token, $e->getMessage());
            }
        }

        return true;
    }

    public function sendToMany($tokens, $title, $body, $data = [])
    {
        foreach ($tokens as $token) {
            try {
                $stringData = array_map('strval', is_array($data) ? $data : []);
                $message = CloudMessage::withTarget('token', $token)
                    ->withNotification(Notification::create($title, $body))
                    ->withData($stringData);
                $this->messaging()->send($message);
            } catch (\Exception $e) {
                $this->handleSendFailure($token, $e->getMessage());
            }
        }

        return true;
    }

    private function handleSendFailure(string $token, string $error): void
    {
        Log::error('FCM failed', [
            'token' => $token,
            'error' => $error,
        ]);

        $staleToken = str_contains(strtolower($error), 'notfound')
            || str_contains(strtolower($error), 'unregistered')
            || str_contains(strtolower($error), 'invalidregistration')
            || str_contains(strtolower($error), 'requested entity was not found');

        if ($staleToken) {
            FcmToken::where('token', $token)->delete();
        }
    }

    private function messaging()
    {
        if ($this->messaging) {
            return $this->messaging;
        }

        $this->messaging = (new Factory)
            ->withServiceAccount(FirebaseCredentials::path())
            ->createMessaging();

        return $this->messaging;
    }
}
