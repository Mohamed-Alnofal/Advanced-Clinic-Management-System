<?php

namespace Modules\Doctors\Http\Controllers;

use Illuminate\Http\Request;
use Modules\Core\Http\Controllers\Controller;
use Modules\Doctors\Services\DoctorReviewService;

class DoctorReviewController extends Controller
{
    public function __construct(
        private DoctorReviewService $service
    ) {}

    public function rate(Request $request)
    {
        $request->validate([
            'doctor_id' => 'required|exists:doctors,id',
            'rating' => 'required|integer|min:1|max:5',
        ]);

        $result = $this->service->rateDoctor($request->all(), auth()->user());
        $status = (int) ($result['status'] ?? 200);
        unset($result['status']);

        return response()->json($result, $status);
    }
}
