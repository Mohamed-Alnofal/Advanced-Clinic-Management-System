<?php

namespace Modules\Core\Http\Controllers;

use Illuminate\Http\Request;
use Modules\Core\Services\FcmService;
use Modules\Core\Http\Controllers\Controller;
use Modules\Users\Models\FcmToken;

class FcmTokenController extends Controller
{
    public function __construct(private FcmService $fcm)
    {
    }

    public function store(Request $request)
    {
        $request->validate([
            'token' => 'required|string|min:10',
        ]);

        $userId = auth()->id();
        if (! $userId) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $this->fcm->storeToken($userId, $request->token);

        return response()->json([
            'message' => 'Token saved successfully',
            'tokens_for_user' => FcmToken::where('user_id', $userId)->count(),
        ]);
    }

    public function test()
    {
        $userId = auth()->id();
        if (! $userId) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $this->fcm->sendToUser(
            $userId,
            'Test Notification',
            'This is a test message',
            ['type' => 'test']
        );

        return response()->json([
            'message' => 'Notification sent',
            'tokens_for_user' => FcmToken::where('user_id', $userId)->count(),
        ]);
    }
}
