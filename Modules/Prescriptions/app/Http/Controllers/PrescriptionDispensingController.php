<?php

namespace Modules\Prescriptions\Http\Controllers;

use Illuminate\Http\Request;
use Modules\Core\Http\Controllers\Controller;
use Modules\Pharmacy\Models\Pharmacy;
use Modules\Prescriptions\Services\PrescriptionDispensingService;

class PrescriptionDispensingController extends Controller
{
    public function __construct(
        protected PrescriptionDispensingService $service
    ) {
    }


    /**
     * Scan / verify QR.
     */
    public function scan(Request $request)
    {
        $data = $request->validate([
            'qr' => [
                'required',
                'string',
            ],
        ]);

        return response()->json(
            $this->service->verifyQr(
                $data['qr']
            )
        );
    }


    /**
     * Dispense prescription.
     */
    public function dispense(Request $request)
    {
        $data = $request->validate([

            'qr' => [
                'required',
                'string',
            ],

            'pharmacy_id' => [
                'required',
                'integer',
                'exists:pharmacies,id',
            ],
        ]);

        $pharmacyId = $this->ownedPharmacyId((int) $data['pharmacy_id']);

        return response()->json(
            $this->service->dispense(
                $data['qr'],
                $pharmacyId,
                (int) auth()->id()
            )
        );
    }


    /**
     * Download archived prescription.
     */
    public function downloadArchive(
        Request $request,
        int $dispensingId
    ) {
        $data = $request->validate([
            'pharmacy_id' => [
                'required',
                'integer',
                'exists:pharmacies,id',
            ],
        ]);

        return $this->service->downloadArchive(
            $dispensingId,
            $this->ownedPharmacyId((int) $data['pharmacy_id'])
        );
    }

    public function index(Request $request)
{
    $data = $request->validate([
        'pharmacy_id' => 'required|integer|exists:pharmacies,id',
        'per_page' => 'nullable|integer|min:1|max:100',
    ]);

    return response()->json(
        $this->service->index(
            $this->ownedPharmacyId((int) $data['pharmacy_id']),
            $data['per_page'] ?? 15
        )
    );
}

public function archivedFiles(Request $request)
{
    $data = $request->validate([
        'pharmacy_id' => [
            'required',
            'integer',
            'exists:pharmacies,id',
        ],

        'per_page' => [
            'nullable',
            'integer',
            'min:1',
            'max:100',
        ],
    ]);

    return response()->json(
        $this->service->archivedFiles(
            $this->ownedPharmacyId((int) $data['pharmacy_id']),
            (int) ($data['per_page'] ?? 15)
        )
    );
}

    private function ownedPharmacyId(int $pharmacyId): int
    {
        $pharmacistId = auth()->user()?->pharmacist?->id;
        abort_if(! $pharmacistId, 403, 'Pharmacist profile not found');

        $owns = Pharmacy::query()
            ->whereKey($pharmacyId)
            ->where('pharmacist_id', $pharmacistId)
            ->exists();

        abort_unless($owns, 403, 'This pharmacy does not belong to you');

        return $pharmacyId;
    }
}