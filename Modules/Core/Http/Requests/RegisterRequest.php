<?php

namespace Modules\Core\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
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
    public function rules()
    {
        return [
            'name' => 'required|string',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:8',
            'phone' => 'nullable|string|max:30|unique:users',
            'role' => 'required|in:user,doctor,pharmacist',

            // doctor
            'specialization_id' => 'required_if:role,doctor|exists:specializations,id',
            'governorate_id' => 'required_if:role,doctor|exists:governorates,id',
            'bio' => 'nullable|string',
            'experience_years' => 'nullable|integer',
            'price' => 'nullable|numeric',

            // 'image' => 'nullable|image|max:2048',

            // 🔥 multiple certificates
            'medical_certificates' => 'required_if:role,doctor|array',
            'medical_certificates.*' => 'file|mimes:pdf,jpg,jpeg,png|max:2048',

            // location
            'clinic_latitude' => 'nullable|numeric',
            'clinic_longitude' => 'nullable|numeric',
            'clinic_address' => 'nullable|string',
                
            'age' => 'nullable|integer|min:0|max:120',
            'gender' => 'nullable|in:male,female',
            'image' => 'required_if:role,doctor,pharmacist|file|mimes:pdf,jpg,png,jpeg|max:2048',
            'pharmacy_name' => 'required_if:role,pharmacist|nullable|string',

        ];
    }
}
