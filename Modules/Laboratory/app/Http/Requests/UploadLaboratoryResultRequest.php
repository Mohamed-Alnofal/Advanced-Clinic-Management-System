<?php

namespace Modules\Laboratory\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadLaboratoryResultRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'result_file' => [
                'required',
                'file',
                'mimes:jpg,jpeg,png,pdf',
                'max:10240',
            ],
        ];
    }
}