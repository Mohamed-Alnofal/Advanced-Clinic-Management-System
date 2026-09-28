<?php

namespace Modules\Disputes\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

use Modules\Core\Http\Controllers\Controller;

use Modules\Disputes\Models\Dispute;

use Modules\Doctors\Models\Doctor;

use Modules\Pharmacist\Models\Pharmacist;


class DisputesController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([

            'doctor_id' => 'nullable|exists:doctors,id',

            'pharmacist_id' => 'nullable|exists:pharmacists,id',

            'subject' => 'required|string|max:255',

            'description' => 'required|string',

            'priority' => 'nullable|in:low,medium,high,critical',
        ]);

        /*
        |--------------------------------------------------------------------------
        | Prevent both empty
        |--------------------------------------------------------------------------
        */
        if (
            !$request->doctor_id &&
            !$request->pharmacist_id
        ) {
            return response()->json([
                'message' => 'doctor_id or pharmacist_id is required'
            ], 422);
        }

        if ($request->doctor_id) {
            $hasVisit = app(\Modules\Doctors\Services\DoctorReviewService::class)
                ->userHasCompletedVisit((int) Auth::id(), (int) $request->doctor_id);
            if (! $hasVisit) {
                return response()->json([
                    'message' => 'You can report a doctor only after a completed clinic or video appointment.',
                ], 422);
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Doctor dispute
        |--------------------------------------------------------------------------
        */
        $defendantId = null;

        if ($request->doctor_id) {

            $doctor = Doctor::with('user')
                ->findOrFail($request->doctor_id);

            $defendantId = $doctor->user_id;
        }

        /*
        |--------------------------------------------------------------------------
        | Pharmacist dispute
        |--------------------------------------------------------------------------
        */
        if ($request->pharmacist_id) {

            $pharmacist = Pharmacist::with('user')
                ->findOrFail($request->pharmacist_id);

            $defendantId = $pharmacist->user_id;
        }

        /*
        |--------------------------------------------------------------------------
        | Create dispute
        |--------------------------------------------------------------------------
        */
        $dispute = Dispute::create([

            'complainant_id' => Auth::id(),

            'defendant_id' => $defendantId,

            'doctor_id' => $request->doctor_id,

            'pharmacist_id' => $request->pharmacist_id,

            'subject' => $request->subject,

            'description' => $request->description,

            'priority' => $request->priority ?? 'medium',

            'status' => 'open',
        ]);

        return response()->json([

            'message' => 'Dispute created successfully',

            'dispute' => $dispute
        ]);
    }

    public function myDisputes()
    {
        $userId = Auth::id();

        $disputes = Dispute::with([
            'complainant',
            'defendant'
        ])
        ->where('complainant_id', $userId)
        ->latest()
        ->paginate(10);

        return response()->json($disputes);
    }
}