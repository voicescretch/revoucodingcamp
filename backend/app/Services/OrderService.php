<?php

namespace App\Services;

use App\Exceptions\TableNotAvailableException;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Table;
use App\Repositories\OrderRepository;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderService
{
    public function __construct(private OrderRepository $orderRepository) {}

    /**
     * Generate a unique order_code in the format 'ORD-XXXXXX'.
     */
    private function generateUniqueOrderCode(): string
    {
        do {
            $code = 'ORD-' . strtoupper(Str::random(6));
        } while (Order::where('order_code', $code)->exists());

        return $code;
    }

    /**
     * Generate an order_number based on current timestamp.
     */
    private function generateOrderNumber(): string
    {
        return 'ORD-' . now()->format('YmdHis') . '-' . rand(100, 999);
    }

    /**
     * Validate that all items have available products.
     * Throws \Exception with code 422 if any product is unavailable.
     */
    private function validateItemsAvailability(array $items): void
    {
        foreach ($items as $item) {
            $product = Product::find($item['product_id']);

            if (!$product || $product->is_available !== true) {
                $name = $product ? $product->name : "ID #{$item['product_id']}";
                throw new \Exception("Produk '{$name}' tidak tersedia.", 422);
            }
        }
    }

    /**
     * Create order items for a given order.
     */
    private function createOrderItems(Order $order, array $items): void
    {
        foreach ($items as $item) {
            $product = Product::find($item['product_id']);

            OrderItem::create([
                'order_id'   => $order->id,
                'product_id' => $product->id,
                'quantity'   => $item['quantity'],
                'unit_price' => $product->sell_price,
                'subtotal'   => $product->sell_price * $item['quantity'],
            ]);
        }
    }

    /**
     * Create a self-order from a customer via QR code scan.
     * $tableIdentifier is the table_number value.
     */
    public function createSelfOrder(array $items, string $tableIdentifier): Order
    {
        $this->validateItemsAvailability($items);

        $table = Table::where('table_number', $tableIdentifier)->first();

        if (!$table || $table->status !== 'available') {
            $status = $table ? $table->status : 'not found';
            throw new \Exception("Meja {$tableIdentifier} tidak tersedia. Status saat ini: {$status}.", 409);
        }

        return DB::transaction(function () use ($items, $table) {
            $order = Order::create([
                'order_number' => $this->generateOrderNumber(),
                'order_code'   => $this->generateUniqueOrderCode(),
                'table_id'     => $table->id,
                'created_by'   => auth()->id(),
                'order_type'   => 'self_order',
                'status'       => 'pending',
            ]);

            $this->createOrderItems($order, $items);

            $table->update(['status' => 'occupied']);

            return $order->load('orderItems');
        });
    }

    /**
     * Create an order by a cashier (dine_in or take_away).
     */
    public function createCashierOrder(array $items, string $type, ?int $tableId): Order
    {
        $this->validateItemsAvailability($items);

        $table = null;

        if ($type === 'dine_in') {
            $table = Table::find($tableId);

            if (!$table || $table->status !== 'available') {
                $tableNumber = $table ? $table->table_number : (string) $tableId;
                $status      = $table ? $table->status : 'not found';
                throw new TableNotAvailableException($tableNumber, $status);
            }
        }

        return DB::transaction(function () use ($items, $type, $table) {
            $order = Order::create([
                'order_number' => $this->generateOrderNumber(),
                'order_code'   => $this->generateUniqueOrderCode(),
                'table_id'     => $table?->id,
                'created_by'   => auth()->id(),
                'order_type'   => $type,
                'status'       => 'pending',
            ]);

            $this->createOrderItems($order, $items);

            if ($table) {
                $table->update(['status' => 'occupied']);
            }

            return $order->load('orderItems');
        });
    }

    /**
     * Update the status of an order.
     * Frees the table when status becomes completed or cancelled.
     */
    public function updateStatus(Order $order, string $status, ?string $reason = null): Order
    {
        $data = ['status' => $status];

        if ($status === 'cancelled') {
            $data['cancellation_reason'] = $reason;
        }

        $order->update($data);

        if (in_array($status, ['completed', 'cancelled']) && $order->table_id) {
            $order->table()->update(['status' => 'available']);
        }

        return $order->fresh();
    }

    /**
     * Find an order by its order_code or throw ModelNotFoundException.
     */
    public function findByCode(string $orderCode): Order
    {
        $order = $this->orderRepository->findByCode($orderCode);

        if (!$order) {
            throw new ModelNotFoundException("Order dengan kode '{$orderCode}' tidak ditemukan.");
        }

        return $order;
    }
}
