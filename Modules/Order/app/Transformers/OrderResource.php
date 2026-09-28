<?php

namespace Modules\Order\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Order\Services\FileService;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'payment_status' => $this->payment_status,
            'total_price' => $this->total_price,
            'delivery_address' => $this->delivery_address,

            // 👨‍⚕️ pharmacist
            'pharmacist' => $this->pharmacist?->name,

            // 📦 items + prescription لكل item
            'items' => $this->whenLoaded('items', function () {

                return $this->items->map(function ($item) {

                    return [
                        'id' => $item->id,

                        'drug' => $item->drug?->name,
                        'quantity' => $item->quantity,
                        'price' => $item->price,

                        // 📁 prescription لكل دواء
                        'prescription_file' => $item->prescription_file
                            ? app(FileService::class)->url($item->prescription_file)
                            : null,
                    ];
                });
            }),

            'created_at' => $this->created_at?->format('Y-m-d H:i'),
        ];
    }
}