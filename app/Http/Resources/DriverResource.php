<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DriverResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->id,
            'driver_name'        => $this->driver_name,
            'phone'              => $this->phone,
            'is_active'          => $this->is_active,

            'driver_image'       => $this->driver_image,
            'front_id_image'     => $this->front_id_image,
            'back_id_image'      => $this->back_id_image,

            'city'               => $this->city,
            'v_location'         => $this->v_location,
            'h_location'         => $this->h_location,

            'vehicle_type'       => $this->vehicleType ? [
                'id'   => $this->vehicleType->id,
                'type' => $this->vehicleType->type,
                'note' => $this->vehicleType->note,
            ] : null,

            'wallet_balance'     => $this->wallet ? $this->wallet->balance : null,
            'wallet_code'     => $this->wallet ? $this->wallet->wallet_code : null,
            'average_rating' => round($this->averageRating(), 1),
            'ratings_count' => $this->ratings()->count(),
            'ratings' => RatingResource::collection($this->whenLoaded('ratings')),

            'created_at'         => $this->created_at,
            'updated_at'         => $this->updated_at,
        ];
    }
}
