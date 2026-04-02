<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReceiptResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $order = $this->whenLoaded('order');

        return [
            'transaction_number' => $this->transaction_number,
            'datetime'           => $this->created_at->format('Y-m-d H:i:s'),
            'table_number'       => $order && $order->table ? $order->table->table_number : null,
            'items'              => $order ? $order->orderItems->map(fn ($item) => [
                'name'       => $item->product->name,
                'qty'        => $item->quantity,
                'unit_price' => $item->unit_price,
                'subtotal'   => $item->subtotal,
            ]) : [],
            'total_amount'   => $this->total_amount,
            'paid_amount'    => $this->paid_amount,
            'change_amount'  => $this->change_amount,
            'payment_method' => $this->payment_method,
        ];
    }
}
