<?php

namespace Modules\Drug\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DrugResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'indication' => $this->indication,
            'state' => $this->state,
            'pharmacodynamics' => $this->pharmacodynamics,
            'toxicity' => $this->toxicity,
            'half_life' => $this->half_life,
            'route_of_elimination' => $this->route_of_elimination,
            'clearance' => $this->clearance,
            'attribute' => $this->attribute,

            // Prefer pharmacy_drug stock fields set by DrugService; fall back to prices table.
            'price' => $this->resolvePharmacyPrice(),
            'quantity' => $this->resolvePharmacyQuantity(),
            'is_available' => $this->resolveAvailability(),
            'currency' => $this->resolveCurrency(),

            'synonyms' => $this->whenLoaded('synonyms', function () {
                return $this->synonyms->pluck('synonym')->unique()->values();
            }, []),

            'categories' => $this->whenLoaded('categories', function () {
                return $this->categories
                    ->unique('id')
                    ->values()
                    ->map(function ($category) {
                        return [
                            'id' => $category->id,
                            'name' => $category->category,
                        ];
                    });
            }, []),
        ];
    }

    private function resolvePharmacyPrice(): ?string
    {
        $attrs = $this->resource->getAttributes();
        if (array_key_exists('price', $attrs) && $attrs['price'] !== null && $attrs['price'] !== '') {
            return (string) $attrs['price'];
        }

        if ($this->relationLoaded('prices') && $this->prices->isNotEmpty()) {
            return (string) $this->prices->last()->cost;
        }

        return null;
    }

    private function resolvePharmacyQuantity(): ?int
    {
        $attrs = $this->resource->getAttributes();
        if (! array_key_exists('quantity', $attrs) || $attrs['quantity'] === null || $attrs['quantity'] === '') {
            return null;
        }

        return (int) $attrs['quantity'];
    }

    private function resolveAvailability(): ?bool
    {
        $attrs = $this->resource->getAttributes();
        if (! array_key_exists('is_available', $attrs)) {
            return null;
        }

        return (bool) $attrs['is_available'];
    }

    private function resolveCurrency(): string
    {
        if ($this->relationLoaded('prices') && $this->prices->isNotEmpty()) {
            return (string) ($this->prices->last()->currency ?: 'SYP');
        }

        return 'SYP';
    }
}
