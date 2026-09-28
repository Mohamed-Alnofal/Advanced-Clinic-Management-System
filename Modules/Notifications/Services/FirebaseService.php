<?php

namespace Modules\Notifications\Services;

use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\AndroidConfig;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Modules\Core\Support\FirebaseCredentials;

class FirebaseService
{
    private $messaging;

    public function __construct()
    {
        $this->messaging = (new Factory)
            ->withServiceAccount(FirebaseCredentials::path())
            ->createMessaging();
    }

    public function sendToToken(string $token, string $title, string $body, array $data = [])
    {
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

        return $this->messaging->send($message);
    }
}
