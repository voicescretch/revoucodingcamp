<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'sku'                 => $this->sku,
            'name'                => $this->name,
            'description'         => $this->description,
            'category_id'         => $this->category_id,
            'category'            => $this->whenLoaded('category', fn () => $this->category ? [
                'id'   => $this->category->id,
                'name' => $this->category->name,
            ] : null),
            'unit'                => $this->unit,
            'buy_price'           => $this->buy_price,
            'sell_price'          => $this->sell_price,
            'stock'               => $this->stock,
            'low_stock_threshold' => $this->low_stock_threshold,
            'is_available'        => $this->is_available,
            'image_path'          => $this->image_path,
            'created_at'          => $this->created_at,
            'updated_at'          => $this->updated_at,
        ];
    }
}
