<?php

namespace Modules\Consultation\Services;

use Modules\Consultation\Models\Consultation;
use Modules\Users\Models\User;
use Modules\Consultation\Services\LiveKitService;
class TokenGeneratorService
{
    public function __construct(
        private readonly LiveKitService $liveKitService,
    ) {}

    public function generate(Consultation $consultation, User $user, int $ttlSeconds = 3600): array
    {
        $roomName = $consultation->room_name;
        if ($roomName === null || trim($roomName) === '') {
            throw new \RuntimeException('Consultation room is not configured.');
        }

        $token = $this->liveKitService->generateToken(
            $user->id,
            $user->name ?? 'User',
            $roomName,
            $ttlSeconds,
        );

        $livekitUrl = config('livekit.url') ?: env('LIVEKIT_URL', '');
        if ($livekitUrl === '') {
            throw new \RuntimeException('LiveKit URL is not configured.');
        }

        return [
            'token' => $token,
            'room_name' => $roomName,
            'livekit_url' => $livekitUrl,
            'user_id' => $user->id,
            'role' => 'participant',
        ];
    }
}
