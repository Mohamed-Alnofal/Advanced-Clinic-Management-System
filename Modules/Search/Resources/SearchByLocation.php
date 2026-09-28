<?php

namespace Modules\Search\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SearchByLocation extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'doctor_id' => $this->id,

            'name' => $this->user->name ?? null,

            'specialization' => $this->specialization->name ?? null,

            'bio' => $this->bio,

            'experience_years' => $this->experience_years,

            'image' => $this->image,

            'distance' => round($this->distance, 2),

            'latitude' => $this->latitude,

            'longitude' => $this->longitude,
        ];
    }
}
