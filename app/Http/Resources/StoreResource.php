<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StoreResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                         => $this->id,
            'store_name'                 => $this->store_name,
            'phone'                => $this->phone,
            'store_owner_name'           => $this->store_owner_name,

            'commercial_register_image'  => $this->commercial_register_image,
            'store_logo'                 => $this->store_logo,

            'city'                       => $this->city,
            'v_location'                 => $this->v_location,
            'h_location'                 => $this->h_location,
            'average_rating' => round($this->averageRating(), 1),
            'ratings_count' => $this->ratings()->count(),

            'categories' => CategoryResource::collection($this->whenLoaded('categories')),
            'subcategories' => SubCategoryResource::collection($this->whenLoaded('subCategories')),
            'ratings' => RatingResource::collection($this->whenLoaded('ratings')),


            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
