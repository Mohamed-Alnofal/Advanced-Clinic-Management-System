<?php

namespace Modules\Admin\Http\Controllers;

use Illuminate\Http\Request;
use Modules\Core\Http\Controllers\Controller;
use Modules\MedicalFacility\Models\MedicalFacility;

class AdminPharmacyController extends Controller
{
    public function unverifiedPharmacies()
    {
        $pharmacies = MedicalFacility::query()
            ->where('type', 'pharmacy')
            ->where(function ($q) {
                $q->where('status', 'pending')
                    ->orWhere(function ($inner) {
                        $inner->where('is_verified', false)
                            ->where(function ($s) {
                                $s->whereNull('status')->orWhere('status', '!=', 'rejected');
                            });
                    });
            })
            ->with([
                'governorate',
                'pharmacy.pharmacist.user',
            ])
            ->latest()
            ->get();

        return response()->json([
            'pharmacies' => $pharmacies,
            'facilities' => $pharmacies,
        ]);
    }

    public function approvePharmacy($id)
    {
        $facility = MedicalFacility::where('type', 'pharmacy')->findOrFail($id);

        if ($facility->is_verified && $facility->status === 'approved') {
            return response()->json([
                'message' => 'This pharmacy is already approved.',
                'facility' => $facility,
            ], 422);
        }

        $facility->update([
            'status' => 'approved',
            'is_verified' => true,
            'is_active' => true,
            'reject_reason' => null,
        ]);

        return response()->json([
            'message' => 'Pharmacy approved successfully',
            'facility' => $facility->fresh(['governorate', 'pharmacy.pharmacist.user']),
        ]);
    }

    public function rejectPharmacy(Request $request, $id)
    {
        $validated = $request->validate([
            'reason' => 'nullable|string|max:1000',
            'reject_reason' => 'nullable|string|max:1000',
        ]);

        $reason = trim((string) ($validated['reject_reason'] ?? $validated['reason'] ?? ''));
        if ($reason === '') {
            return response()->json([
                'message' => 'A rejection reason is required.',
                'errors' => ['reject_reason' => ['A rejection reason is required.']],
            ], 422);
        }

        $facility = MedicalFacility::where('type', 'pharmacy')->findOrFail($id);

        $facility->update([
            'status' => 'rejected',
            'is_verified' => false,
            'is_active' => false,
            'reject_reason' => $reason,
        ]);

        return response()->json([
            'message' => 'Pharmacy rejected successfully',
            'facility' => $facility->fresh(['governorate', 'pharmacy.pharmacist.user']),
        ]);
    }
}
