<?php

namespace App\Repositories;

use App\Models\Order;
use Illuminate\Database\Eloquent\Collection;

class OrderRepository
{
    /**
     * Find an order by its order_code.
     */
    public function findByCode(string $orderCode): ?Order
    {
        return Order::where('order_code', $orderCode)->first();
    }

    /**
     * Find active orders (status NOT IN completed, cancelled).
     * Supports optional filter by status.
     */
    public function findActiveOrders(array $filters = []): Collection
    {
        $query = Order::whereNotIn('status', ['completed', 'cancelled']);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->get();
    }

    /**
     * Find an order with its orderItems and their products eagerly loaded.
     */
    public function findWithItems(int $id): ?Order
    {
        return Order::with(['orderItems.product'])->find($id);
    }
}
