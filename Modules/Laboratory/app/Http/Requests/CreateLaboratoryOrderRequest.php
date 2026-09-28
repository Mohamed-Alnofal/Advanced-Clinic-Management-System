<?php

namespace Modules\Laboratory\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateLaboratoryOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'medical_visit_id' => [
                'required',
                'integer',
                'exists:medical_visits,id',
            ],

            'laboratory_id' => [
                'required',
                'integer',
                'exists:laboratories,id',
            ],

            'tests' => [
                'required',
                'array',
                'min:1',
            ],

            'tests.*' => [
                'required',
                'string',
                'max:255',
            ],

            'doctor_notes' => [
                'nullable',
                'string',
                'max:5000',
            ],
        ];
    }
}