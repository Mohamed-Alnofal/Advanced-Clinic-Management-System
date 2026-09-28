<?php

namespace Modules\Core\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
public function rules(): array
{
    return [

        // user table
        'name' => 'sometimes|string|max:255',
        'email' => 'sometimes|email|unique:users,email,' . auth()->id(),
        'phone' => 'sometimes|string|max:20',
        'date_of_birth' => [
            'sometimes',
            'nullable',
            'date',
            'before:today',
            'after_or_equal:' . now()->subYears(120)->toDateString(),
        ],
        'gender' => 'sometimes|in:male,female',

        // doctor
        'bio' => 'nullable|string',
        'specialization_id' => 'nullable|exists:specializations,id',
        'price' => 'nullable|numeric',
        'experience_years' => 'nullable|integer',

        // pharmacist
        'pharmacy_name' => 'nullable|string|max:255',
        'license_number' => 'nullable|string|max:255',

        // images
        'image' => 'nullable|image|mimes:jpg,jpeg,png',
        'medical_certificate' => 'nullable|file|mimes:pdf,jpg,jpeg,png',
    ];
}
}
