<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomOrderItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'description' => $this->description,
            'pickup_address' => $this->pickup_address,
            'pickup_lat' => $this->pickup_lat ? (float) $this->pickup_lat : null,
            'pickup_lng' => $this->pickup_lng ? (float) $this->pickup_lng : null,
            'order_index' => $this->order_index,
        ];
    }
}
