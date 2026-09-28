<?php

namespace Modules\Ambulance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAmbulanceUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

 public function rules(): array
{
    return [
        'plate_number' => 'required|unique:ambulance_units',
        'ambulance_driver_id' => 'nullable|exists:ambulance_drivers,id',
    ];
}
}