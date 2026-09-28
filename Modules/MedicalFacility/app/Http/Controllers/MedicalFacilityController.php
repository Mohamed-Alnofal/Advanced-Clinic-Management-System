<?php

namespace Modules\MedicalFacility\Http\Controllers;

use Modules\Core\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\MedicalFacility\Factories\MedicalCenterFactory;

class MedicalFacilityController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return view('medicalfacility::index');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('medicalfacility::create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $factory = MedicalCenterFactory::make($request->type);

        $center = $factory->create($request->all());

        return response()->json([
            'message' => 'Created Successfully',
            'data' => $center
        ]);
    }

    /**
     * Show the specified resource.
     */
    public function show($id)
    {
        return view('medicalfacility::show');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        return view('medicalfacility::edit');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id): RedirectResponse
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        //
    }
}
