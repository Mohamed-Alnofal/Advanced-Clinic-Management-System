<?php

namespace Modules\AI\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AnalyzeChartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->doctor !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'lookup_id' => ['required', 'integer', 'min:1'],
            'source' => ['required', 'string', 'in:user,relative'],
            'patient_name' => ['nullable', 'string', 'max:120'],
        ];
    }
}
