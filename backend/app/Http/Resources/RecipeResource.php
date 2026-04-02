<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RecipeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'menu_product_id'  => $this->menu_product_id,
            'raw_material_id'  => $this->raw_material_id,
            'raw_material'     => $this->whenLoaded('rawMaterial', fn () => [
                'id'    => $this->rawMaterial->id,
                'name'  => $this->rawMaterial->name,
                'unit'  => $this->rawMaterial->unit,
                'stock' => $this->rawMaterial->stock,
            ]),
            'quantity_required' => $this->quantity_required,
            'unit'              => $this->unit,
            'created_at'        => $this->created_at,
            'updated_at'        => $this->updated_at,
        ];
    }
}
