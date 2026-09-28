<?php

namespace Modules\Core\Http\Controllers;
use Illuminate\Http\Request;
use Modules\Core\Services\SpecializationService;

class SpecializationController extends Controller
{
    public function __construct(
        protected SpecializationService $specializationService
    ) {}

    /**
     * Get all specializations
     */
    public function index()
    {
        $specializations = $this->specializationService->getAll();

        return response()->json([
            'success' => true,
            'message' => 'Specializations retrieved successfully.',
            'data' => $specializations,
        ]);
    }

    /**
     * Get one specialization
     */
    public function show(int $id)
    {
        $specialization = $this->specializationService->getById($id);

        return response()->json([
            'success' => true,
            'message' => 'Specialization retrieved successfully.',
            'data' => $specialization,
        ]);
    }

    /**
     * Create specialization
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:specializations,name'],
        ]);

        $specialization = $this->specializationService->create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Specialization created successfully.',
            'data' => $specialization,
        ], 201);
    }

    /**
     * Update specialization
     */
    public function update(Request $request, int $id)
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                'unique:specializations,name,' . $id,
            ],
        ]);

        $specialization = $this->specializationService->update(
            $id,
            $validated
        );

        return response()->json([
            'success' => true,
            'message' => 'Specialization updated successfully.',
            'data' => $specialization,
        ]);
    }

    /**
     * Delete specialization
     */
    public function destroy(int $id)
    {
        $this->specializationService->delete($id);

        return response()->json([
            'success' => true,
            'message' => 'Specialization deleted successfully.',
        ]);
    }
}