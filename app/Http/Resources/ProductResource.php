<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'category_id' => $this->category_id,
            'category' => new CategoryResource($this->whenLoaded('category')),
            'name' => $this->name,
            'description' => $this->description,
            'gold_weight' => $this->gold_weight !== null ? (float) $this->gold_weight : null,
            'karat' => $this->karat,
            'gemstone_type' => $this->gemstone_type,
            'gemstone_carat' => $this->gemstone_carat !== null ? (float) $this->gemstone_carat : null,
            'price' => (float) $this->price,
            'stock' => $this->stock,
            'is_active' => $this->is_active,
            'images' => ProductImageResource::collection($this->whenLoaded('images')),
        ];
    }
}