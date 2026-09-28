<?php

namespace Modules\Consultation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveConsultationNotesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'doctor_notes' => ['required', 'string', 'max:5000'],
        ];
    }
}