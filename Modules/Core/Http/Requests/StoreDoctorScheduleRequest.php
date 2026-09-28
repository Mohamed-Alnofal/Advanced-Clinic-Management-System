<?php

namespace Modules\Core\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDoctorScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [

            // نوع الاستشارة مرة واحدة فقط
            'consultation_type' => [
                'required',
                Rule::in(['clinic', 'video']),
            ],

            'schedules' => [
                'required',
                'array'
            ],

            // مطلوب فقط إذا كانت الاستشارة داخل العيادة
            'schedules.*.clinic_id' => [
                'nullable',
                'exists:clinics,id',
                'required_if:consultation_type,clinic',
            ],

            'schedules.*.day' => [
                'required',
                Rule::in([
                    'sunday',
                    'monday',
                    'tuesday',
                    'wednesday',
                    'thursday',
                    'friday',
                    'saturday',
                ]),
            ],

            'schedules.*.start_time' => [
                'required',
                'date_format:H:i:s',
            ],

            'schedules.*.end_time' => [
                'required',
                'date_format:H:i:s',
                'after:schedules.*.start_time',
            ],

            'schedules.*.consultation_duration' => [
                'required',
                'integer',
                'min:5',
            ],

            'schedules.*.price' => [
                'required',
                'numeric',
                'min:0',
            ],
        ];
    }
}