<?php

namespace Modules\Search\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Support\PublicMedia;
use Modules\Doctors\Services\DoctorReviewService;

class DoctorSearchResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $meta = app(DoctorReviewService::class)->ratingMeta($this->resource);

        return [
            'id' => $this->id,
            'name' => $this->user?->name,
            'image_url' => PublicMedia::url($this->image),
            'specialization' => $this->specialization?->name ?? 'General',
            'experience_years' => $this->experience_years,
            'price' => $this->price,
            'bio' => $this->bio,
            'is_verified' => (bool) ($this->is_verified ?? false),
            'average_rating' => $meta['average_rating'],
            'ratings_count' => $meta['ratings_count'],
            'rating' => $meta['average_rating'],
            'clinics' => $this->clinics->map(function ($clinic) {
                return [
                    'id' => $clinic->id,
                    'medical_facility' => [
                        'id' => $clinic->medicalCenter?->id,
                        'name' => $clinic->medicalCenter?->name,
                        'address' => $clinic->medicalCenter?->address,
                        'latitude' => $clinic->medicalCenter?->latitude,
                        'longitude' => $clinic->medicalCenter?->longitude,
                    ],
                ];
            }),
        ];
    }
}
