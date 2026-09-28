<?php

namespace Modules\Pharmacy\Http\Controllers;

use Modules\Core\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Modules\Pharmacy\Services\PharmacyService;

class PharmacyController extends Controller
{
    public function __construct(
        protected PharmacyService $service
    ) {}

    public function store(Request $request)
    {
        $data = $request->validate([
            'governorate_id' => 'required|exists:governorates,id',
            'name' => 'required|string|max:255',
            'address' => 'nullable|string',
            'phone' => 'nullable|string|max:30',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'schedules' => 'nullable|array',
            'schedules.*.day' => 'required_with:schedules|string',
            'schedules.*.open_time' => 'nullable',
            'schedules.*.close_time' => 'nullable',
            'schedules.*.is_closed' => 'boolean',
        ]);

        $user = auth()->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        if (! $user->hasRole('pharmacist')) {
            return response()->json(['message' => 'Unauthorized pharmacist'], 403);
        }

        $pharmacist = $user->pharmacist;

        if (! $pharmacist || ! $pharmacist->is_verified) {
            return response()->json(['message' => 'Pharmacist not verified'], 403);
        }

        $result = $this->service->create($data, $pharmacist->id);

        return response()->json([
            'message' => $result['message'],
            'requires_approval' => $result['requires_approval'],
            'data' => $result['pharmacy'],
        ], 201);
    }

    public function index()
    {
        $user = Auth::user();

        if (! $user || ! $user->pharmacist) {
            return response()->json([
                'message' => 'Unauthorized pharmacist',
            ], 403);
        }

        return response()->json([
            'data' => $this->service->getMyPharmacies(
                $user->pharmacist->id
            ),
        ]);
    }

    public function show($id)
    {
        $user = Auth::user();

        if (! $user || ! $user->pharmacist) {
            return response()->json([
                'message' => 'Unauthorized pharmacist',
            ], 403);
        }

        return response()->json([
            'data' => $this->service->getById($id, $user->pharmacist->id),
        ]);
    }

    public function update(Request $request, $id)
    {
        $data = $request->validate([
            'name' => 'nullable|string',
            'address' => 'nullable|string',
            'phone' => 'nullable|string|max:30',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'schedules' => 'nullable|array',
            'schedules.*.day' => 'required_with:schedules|string',
            'schedules.*.open_time' => 'nullable',
            'schedules.*.close_time' => 'nullable',
            'schedules.*.is_closed' => 'boolean',
        ]);

        $user = auth()->user();

        if (! $user->hasRole('pharmacist') || ! $user->pharmacist) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        try {
            $pharmacy = $this->service->update($id, $data, $user->pharmacist->id);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => collect($e->errors())->flatten()->first() ?? 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        }

        return response()->json([
            'message' => 'Pharmacy updated successfully',
            'data' => $pharmacy,
        ]);
    }

    public function destroy($id)
    {
        $user = auth()->user();

        if (! $user || ! $user->pharmacist) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        try {
            $this->service->delete($id, $user->pharmacist->id);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Pharmacy not found or cannot be deleted',
            ], 404);
        }

        return response()->json([
            'message' => 'Deleted successfully',
        ]);
    }
}
