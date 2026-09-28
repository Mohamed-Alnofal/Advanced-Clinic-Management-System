<?php

namespace Modules\Ambulance\Http\Controllers;

use Modules\Core\Http\Controllers\Controller;
use Modules\Ambulance\Models\AmbulanceUnit;
use Modules\Ambulance\Services\AmbulanceUnitService;
use Modules\Ambulance\Http\Requests\StoreAmbulanceUnitRequest;
use Illuminate\Http\Request;
use Modules\Ambulance\Models\AmbulanceDriver;
use Modules\Ambulance\Models\AmbulanceCompany;
class AmbulanceUnitController extends Controller
{
    public function __construct(
        private AmbulanceUnitService $service
    ) {}

    public function index()
    {
        $company = auth()->user()->ambulanceCompany;

        if (! $company) {
            return response()->json([
                'success' => true,
                'has_company' => false,
                'message' => 'No ambulance company found. Create your company first.',
                'data' => [],
            ]);
        }

        return response()->json(
            $this->service->index($company->id)
        );
    }

    public function store(StoreAmbulanceUnitRequest $request)
    {
        $company = auth()->user()->ambulanceCompany;

        if (!$company) {
            return response()->json([
                'message' => 'No ambulance company found'
            ], 403);
        }

        return response()->json(
            $this->service->store($company->id, $request->validated()),
            201
        );
    }

    public function update(Request $request, AmbulanceUnit $unit)
    {
        $this->assertOwnsUnit($unit);

        return response()->json(
            $this->service->update($unit, $request->all())
        );
    }

    public function updateLocation(Request $request, AmbulanceUnit $unit)
    {
        $this->assertOwnsUnit($unit);

        $validated = $request->validate([
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
        ]);

        return response()->json(
            $this->service->updateLocation($unit, $validated)
        );
    }

    public function updateStatus(Request $request, AmbulanceUnit $unit)
    {
        $this->assertOwnsUnit($unit);

        $validated = $request->validate([
            'status' => 'required|in:available,busy,offline'
        ]);

        return response()->json(
            $this->service->updateStatus($unit, $validated['status'])
        );
    }

    public function destroy(AmbulanceUnit $unit)
    {
        $this->assertOwnsUnit($unit);

        return response()->json(
            $this->service->destroy($unit)
        );
    }

    public function generatePairing(AmbulanceUnit $unit)
    {
        $this->assertOwnsUnit($unit);

        $token = bin2hex(random_bytes(24));
        $unit->update(['device_token' => $token]);

        return response()->json([
            'success' => true,
            'message' => 'Pairing token generated. Configure the driver device with this token for FCM.',
            'unit_id' => $unit->id,
            'device_token' => $token,
        ]);
    }

    private function assertOwnsUnit(AmbulanceUnit $unit): void
    {
        $company = auth()->user()?->ambulanceCompany;
        if (! $company || (int) $unit->ambulance_company_id !== (int) $company->id) {
            abort(403, 'This unit does not belong to your company.');
        }
    }
}