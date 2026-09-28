<?php

namespace Modules\Ambulance\Http\Controllers;

use Illuminate\Http\Request;
use Modules\Core\Http\Controllers\Controller;
use Modules\Ambulance\Services\AmbulanceDriverAuthService;

class AmbulanceDriverAuthController extends Controller
{
    public function __construct(
        private AmbulanceDriverAuthService $service
    ) {}

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $result = $this->service->login(
            $request->email,
            $request->password
        );

        if ($result instanceof \Illuminate\Http\JsonResponse) {
            return $result;
        }

        return response()->json($result);
    }

    public function logout()
    {
        return response()->json(
            $this->service->logout()
        );
    }
}