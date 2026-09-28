<?php

namespace Modules\Clinics\Http\Controllers;

use Modules\Core\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Modules\Clinics\Resources\ClinicResource;
use Modules\Clinics\Services\ClinicService;

class ClinicController extends Controller
{
        public function __construct(
        private ClinicService $service
    ) {
    }
    public function store(Request $request, ClinicService $service)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'address' => 'required|string|max:255',
            'governorate_id' => 'required|exists:governorates,id',
            'type' => 'required|in:clinic,pharmacy,hospital,ambulance',
            'phone' => 'nullable|string|max:20',
            'image' => 'nullable|image|max:2048',
        ]);

        try {
            $result = $service->create(
                auth()->user(),
                $request->all() + [
                    'image' => $request->file('image'),
                ]
            );
        } catch (ValidationException $e) {
            return response()->json([
                'message' => collect($e->errors())->flatten()->first()
                    ?? 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'Could not create clinic. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }

        return response()->json([
            'message' => $result['message'],
            'clinic' => $this->clinicPayload($result['clinic']),
        ], 201);
    }
   

    public function update(Request $request, $clinicId, ClinicService $service)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'address' => 'required|string|max:255',
            'governorate_id' => 'required|exists:governorates,id',
            'phone' => 'nullable|string|max:20',
            'image' => 'nullable|image|max:2048',
        ]);

        try {
            $clinic = $service->update(
                auth()->user(),
                $clinicId,
                $request->all() + [
                    'image' => $request->file('image'),
                ]
            );
        } catch (ValidationException $e) {
            return response()->json([
                'message' => collect($e->errors())->flatten()->first() ?? 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        }

        return response()->json([
            'message' => 'Clinic updated successfully',
            'clinic' => $this->clinicPayload($clinic),
        ]);
    }

    public function myClinics(ClinicService $service)
    {
        try {
            $clinics = $service->getDoctorClinics(auth()->user());

            return response()->json([
                'message' => 'Doctor clinics retrieved successfully',
                'data' => ClinicResource::collection($clinics),
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => collect($e->errors())->flatten()->first()
                    ?? 'Validation failed',
                'errors' => $e->errors(),
                'data' => [],
            ], 422);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'Could not load clinics. Please try again.',
                'data' => [],
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    public function destroy($clinicId, ClinicService $service)
    {
        try {
            $service->delete(auth()->user(), $clinicId);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => collect($e->errors())->flatten()->first() ?? 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        }

        return response()->json([
            'message' => 'Clinic removed from your account successfully',
        ]);
    }

    private function clinicPayload($clinic): array
    {
        $center = $clinic->medicalCenter;

        if (! $center) {
            return [
                'id' => $clinic->id,
                'medical_facility_id' => $clinic->medical_facility_id,
                'medical_center' => null,
            ];
        }

        return [
            'id' => $clinic->id,
            'medical_facility_id' => $clinic->medical_facility_id,
            'medical_center' => [
                'id' => $center->id,
                'name' => $center->name,
                'latitude' => $center->latitude,
                'longitude' => $center->longitude,
                'address' => $center->address,
                'governorate_id' => $center->governorate_id,
                'type' => $center->type,
                'phone' => $center->phone,
                'image' => $center->image,
                'status' => $center->status ?? 'pending',
                'is_verified' => (bool) $center->is_verified,
                'is_active' => (bool) $center->is_active,
                'reject_reason' => $center->reject_reason,
            ],
        ];
    }

    
    /**
     * Get all pending medical facility requests.
     */
    public function pending(Request $request)
    {
        try {
            $perPage = min(
                (int) $request->input('per_page', 20),
                100
            );

            $type = $request->input('type');
            $facilities = $this->service->getPendingRequests(
                $perPage,
                is_string($type) ? $type : null
            );

            return response()->json([
                'message' => 'Pending requests retrieved successfully.',
                'facilities' => $facilities,
            ]);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'Could not load pending facilities.',
                'facilities' => [
                    'data' => [],
                    'total' => 0,
                    'current_page' => 1,
                    'last_page' => 1,
                ],
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 200);
        }
    }

    /**
     * Approve a medical facility.
     */
    public function approve(int $facility)
    {
        try {

            $facility = $this->service->approve($facility);

        } catch (ValidationException $e) {

            return response()->json([
                'message' => collect($e->errors())
                    ->flatten()
                    ->first() ?? 'Validation failed',

                'errors' => $e->errors(),
            ], 422);
        }

        return response()->json([
            'message' => 'Request approved successfully.',
            'facility' => $facility,
        ]);
    }

    /**
     * Reject a medical facility.
     */
    public function reject(
        Request $request,
        int $facility
    ) {
        $validated = $request->validate([
            'reject_reason' => 'required|string|max:1000',
        ]);

        try {

            $facility = $this->service->reject(
                $facility,
                $validated['reject_reason']
            );

        } catch (ValidationException $e) {

            return response()->json([
                'message' => collect($e->errors())
                    ->flatten()
                    ->first() ?? 'Validation failed',

                'errors' => $e->errors(),
            ], 422);
        }

        return response()->json([
            'message' => 'Request rejected successfully.',
            'facility' => $facility,
        ]);
    }
}
