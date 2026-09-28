<?php

namespace Modules\Notifications\Services;

use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\AndroidConfig;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Modules\Core\Support\FirebaseCredentials;

class FirebaseNotificationService
{
    protected $messaging;

    public function __construct()
    {
        $this->messaging = (new Factory)
            ->withServiceAccount(FirebaseCredentials::path())
            ->createMessaging();
    }

    public function send(
        string $token,
        string $title,
        string $body,
        array $data = []
    ): bool {
        try {
            $stringData = array_map('strval', $data);

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

            $this->messaging->send($message);

            return true;
        } catch (\Throwable $e) {
            logger()->error('Firebase Error: '.$e->getMessage());

            return false;
        }
    }
}
