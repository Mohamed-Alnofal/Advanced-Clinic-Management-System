<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PharmacyResource extends JsonResource
{
    /**
     * تحويل الصيدلية إلى array مناسب للAPI
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->medicalFacility->name ?? null,     // اسم الصيدلية
            'address' => $this->medicalFacility->address ?? null, // العنوان
            'phone' => $this->medicalFacility->phone ?? null,    // رقم الهاتف
            'distance' => $this->distance ?? null,               // المسافة (كم)
            'distance_text' => $this->distance ? round($this->distance, 1) . ' كم' : null,
            'is_open' => $this->is_open ?? true,                 // مفتوحة الآن؟
            'drugs' => DrugResource::collection($this->whenLoaded('drugs')), // الأدوية المتوفرة
        ];
    }
}