<?php

namespace Modules\Consultation\Services;

use Agence104\LiveKit\AccessToken;
use Agence104\LiveKit\AccessTokenOptions;
use Agence104\LiveKit\VideoGrant;

class LiveKitService
{
    public function generateToken(
        int $userId,
        string $userName,
        string $roomName,
        int $ttlSeconds = 3600
    ): string {
        $options = (new AccessTokenOptions())
            ->setIdentity((string) $userId)
            ->setName($userName);

        if (method_exists($options, 'setTtl')) {
            $options->setTtl(max(300, $ttlSeconds));
        }

        $token = new AccessToken(
            config('livekit.key'),
            config('livekit.secret'),
            $options,
        );

        $grant = new VideoGrant();
        $grant->setRoomJoin(true);
        $grant->setRoomName($roomName);
        $grant->setCanPublish(true);
        $grant->setCanSubscribe(true);

        $token->setGrant($grant);

        return $token->toJwt();
    }
}