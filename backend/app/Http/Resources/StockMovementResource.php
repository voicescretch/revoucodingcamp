<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockMovementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'product_id'     => $this->product_id,
            'product'        => $this->whenLoaded('product', fn () => $this->product?->name),
            'type'           => $this->type,
            'quantity'       => $this->quantity,
            'stock_before'   => $this->stock_before,
            'stock_after'    => $this->stock_after,
            'reference_type' => $this->reference_type,
            'reference_id'   => $this->reference_id,
            'notes'          => $this->notes,
            'created_by'     => $this->created_by,
            'created_at'     => $this->created_at,
        ];
    }
}
