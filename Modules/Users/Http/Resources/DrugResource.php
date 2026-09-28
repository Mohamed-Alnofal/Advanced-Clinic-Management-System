<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DrugResource extends JsonResource
{
    /**
     * تحويل الدواء إلى array مناسب للAPI
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,                           // الاسم التجاري
            'scientific_name' => $this->scientific_name,     // الاسم العلمي
            'price' => $this->price,                          // السعر
            'requires_prescription' => $this->requires_prescription, // يحتاج روشتة؟
            'quantity' => $this->quantity,                    // الكمية المتوفرة
            'alternatives' => DrugResource::collection($this->whenLoaded('alternatives')), // بدائل
        ];
    }
}