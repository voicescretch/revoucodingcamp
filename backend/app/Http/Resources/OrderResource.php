<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'order_number'        => $this->order_number,
            'order_code'          => $this->order_code,
            'user_id'             => $this->user_id,
            'table_id'            => $this->table_id,
            'table'               => $this->whenLoaded('table', fn () => [
                'table_number' => $this->table->table_number,
            ]),
            'created_by'          => $this->created_by,
            'order_type'          => $this->order_type,
            'status'              => $this->status,
            'notes'               => $this->notes,
            'cancellation_reason' => $this->cancellation_reason,
            'items'               => OrderItemResource::collection($this->whenLoaded('orderItems')),
            'created_at'          => $this->created_at,
            'updated_at'          => $this->updated_at,
        ];
    }
}
