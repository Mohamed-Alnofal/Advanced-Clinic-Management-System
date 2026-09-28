<?php

namespace Modules\Ambulance\Http\Controllers;

use Illuminate\Http\Request;
use Modules\Core\Http\Controllers\Controller;
use Modules\Ambulance\Models\AmbulanceDriver;
use Modules\Ambulance\Services\AmbulanceDriverService;

class AmbulanceDriverController extends Controller
{
    public function __construct(
        private AmbulanceDriverService $service
    ) {}

    public function index()
    {
        $company = auth()->user()?->ambulanceCompany;
        if (! $company) {
            return response()->json([
                'success' => true,
                'has_company' => false,
                'message' => 'No ambulance company found. Create your company first.',
                'data' => [],
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Drivers retrieved successfully.',
            'data' => $this->service->index($company->id),
        ]);
    }

    public function store(Request $request)
{
    $company = auth()->user()?->ambulanceCompany;
    if (! $company) {
        return response()->json(['message' => 'No ambulance company found'], 403);
    }

    $data = $request->validate([
        'name' => 'required|string',
        'phone' => 'required|unique:ambulance_drivers',
        'email' => 'required|email|unique:ambulance_drivers',
        'password' => 'required|min:8',
    ]);

    $data['ambulance_company_id'] = $company->id;

    return response()->json(
        $this->service->store(auth()->id(), $data),
        201
    );
}

    public function update(Request $request, AmbulanceDriver $driver)
    {
        $this->assertOwnsDriver($driver);

        return response()->json(
            $this->service->update(
                $driver,
                $request->all()
            )
        );
    }

    public function destroy(AmbulanceDriver $driver)
    {
        $this->assertOwnsDriver($driver);

        return response()->json(
            $this->service->destroy($driver)
        );
    }

    private function assertOwnsDriver(AmbulanceDriver $driver): void
    {
        $company = auth()->user()?->ambulanceCompany;
        if (! $company || (int) $driver->ambulance_company_id !== (int) $company->id) {
            abort(403, 'This driver does not belong to your company.');
        }
    }
}