<?php

namespace Modules\Core\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EmergencyTriageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'text_transcript' => ['required', 'string', 'min:10', 'max:5000'],
        ];
    }

    public function messages(): array
    {
        return [
            'latitude.required' => 'إحداثيات الموقع (خط العرض) مطلوبة.',
            'longitude.required' => 'إحداثيات الموقع (خط الطول) مطلوبة.',
            'text_transcript.required' => 'وصف الحالة الطارئة مطلوب.',
            'text_transcript.min' => 'وصف الحالة يجب أن يكون 10 أحرف على الأقل.',
        ];
    }
}
