<?php

namespace Modules\Users\Http\Controllers;

use Modules\Core\Http\Controllers\Controller;
// use Modules\Users\Services\ProfileService;
// use Modules\Users\Http\Requests\UpdateProfileRequest;
use Illuminate\Http\Request;
use Modules\Core\Http\Requests\UpdateProfileRequest;
use Modules\Core\Services\ProfileService;

class ProfileController extends Controller
{
    // 👤 بروفايل المستخدم نفسه
    public function myProfile(ProfileService $service)
    {
        // $user = auth('api')->user();
        return response()->json(
            $service->myProfile(auth()->user())
        );
    }

    public function updateProfile(
            UpdateProfileRequest $request,
            ProfileService $service
        ) {
            return response()->json(

                $service->updateProfile(
                    auth()->user(),
                    $request
                )

            );
        }
    // 🧑‍⚕️ عرض طبيب
    public function showDoctor($id, ProfileService $service)
    {
        return response()->json(
            $service->showDoctor($id)
        );
    }

    // 💊 عرض صيدلي
    public function showPharmacist($id, ProfileService $service)
    {
        return response()->json(
            $service->showPharmacist($id)
        );
    }
}
