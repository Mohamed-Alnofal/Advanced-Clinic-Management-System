<?php

namespace Modules\Admin\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,

            // Spatie role
            'role' => $this->getRoleNames()->first(),

            // status (إذا عندك عمود status أو accessors)
            'status' => $this->status ?? 'active',

            'is_verified' => $this->is_verified,

            'created_at' => $this->created_at?->format('Y-m-d'),

           
        ];
    }
}