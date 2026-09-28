<?php

namespace Modules\Subscription\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubscribeWalletRequest extends FormRequest
{
    // public function authorize(): bool
    // {
    //     return auth()->check();
    // }

    public function rules(): array
    {
        return [
            'plan_id' => [
                'required',
                'integer',
                'exists:subscription_plans,id',
            ],

            'auto_renew' => [
                'sometimes',
                'boolean',
            ],
        ];
    }
}