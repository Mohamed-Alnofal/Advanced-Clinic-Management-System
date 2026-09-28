<?php

namespace Modules\Laboratory\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AnalyzeLaboratoryAssistRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'result_file' => ['required', 'file', 'mimes:pdf,txt', 'max:10240'],
            'gender' => ['nullable', 'string', 'in:male,female,all'],
            'age' => ['nullable', 'integer', 'min:0', 'max:120'],
        ];
    }
}
