<?php

namespace Modules\Ambulance\Http\Controllers;

use Illuminate\Http\Request;
use Modules\Core\Http\Controllers\Controller;
use Modules\Ambulance\Services\AmbulanceCompanyService;

class AmbulanceCompanyController extends Controller
{
    public function __construct(
        protected AmbulanceCompanyService $service
    ) {
    }

    public function myCompany()
    {
        return response()->json(
            $this->service->myCompany(auth()->user())
        );
    }

    public function store(Request $request)
    {
        $request->validate([

            'name' => 'required|string|max:255',

            'license_number' => 'required|string|unique:ambulance_companies',

            'phone' => 'required',

            'address' => 'required',

            'governorate_id' => 'required|exists:governorates,id',

            'latitude' => 'nullable|numeric',

            'longitude' => 'nullable|numeric',

            'description' => 'nullable|string',

            'work_from' => 'nullable|date_format:H:i',

            'work_to' => 'nullable|date_format:H:i',

            'is_24_hours' => 'boolean',

            'logo' => 'nullable|string',
        ]);

        $result = $this->service->createCompany(
            auth()->user(),
            $request->all()
        );

        return response()->json(
            $result,
            $result['success'] ? 201 : 409
        );
    }

    public function updateLocation(Request $request)
{
    $request->validate([
        'latitude' => 'required|numeric',
        'longitude' => 'required|numeric',
    ]);

    $company = $this->service->updateLocation(
        auth()->id(),
        $request->only('latitude', 'longitude')
    );

    return response()->json([
        'message' => 'Location updated successfully.',
        'company' => $company,
    ]);
}

    public function update(Request $request)
    {
        $mine = $this->service->myCompany(auth()->user());
        if (empty($mine['has_company']) || empty($mine['company'])) {
            return response()->json(['message' => 'No ambulance company found.'], 404);
        }

        $company = $mine['company'];

        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'license_number' => 'sometimes|string|unique:ambulance_companies,license_number,'.$company->id,
            'phone' => 'sometimes|string',
            'address' => 'sometimes|string',
            'governorate_id' => 'sometimes|exists:governorates,id',
            'description' => 'nullable|string',
            'logo' => 'nullable|string',
            'work_from' => 'nullable|date_format:H:i',
            'work_to' => 'nullable|date_format:H:i',
            'is_24_hours' => 'sometimes|boolean',
        ]);

        return response()->json([
            'message' => 'Company updated successfully.',
            'company' => $this->service->update(auth()->id(), $data),
        ]);
    }
}