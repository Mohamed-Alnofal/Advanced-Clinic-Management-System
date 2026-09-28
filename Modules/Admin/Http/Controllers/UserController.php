<?php

namespace Modules\Admin\Http\Controllers;
use Modules\Core\Http\Controllers\Controller;
use Modules\Core\Services\UserService;
use Illuminate\Http\Request;
use Modules\Admin\Http\Resources\UserResource;
class UserController extends Controller
{
    public function __construct(
        protected UserService $userService
    ) {}

    public function index(Request $request)
    {
         $users = $this->userService->listUsers($request->all());

        return UserResource::collection($users);

    }

    public function activate($id)
    {
        $user = $this->userService->activate((int)$id);

        return response()->json([
            'message' => 'User activated',
            'data' => $user
        ]);
    }

    public function suspend(Request $request, $id)
    {
        $request->validate([
            'suspension_reason' => 'required|string|max:255'
        ]);

        $user = $this->userService->suspend(
            (int)$id,
            $request->suspension_reason
        );

        return response()->json([
            'message' => 'User suspended',
            'data' => $user
        ]);
    }

    public function destroy($id)
    {
        $this->userService->delete((int)$id);

        return response()->json([
            'message' => 'User deleted'
        ]);
    }
}