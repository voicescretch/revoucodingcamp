<?php

namespace App\Http\Controllers\API;

use App\Exceptions\InvalidPaymentException;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\OrderAlreadyProcessedException;
use App\Http\Controllers\Controller;
use App\Http\Requests\CheckoutRequest;
use App\Http\Resources\ReceiptResource;
use App\Models\Order;
use App\Models\Transaction;
use App\Services\CheckoutService;
use Illuminate\Http\JsonResponse;

class TransactionController extends Controller
{
    public function __construct(
        private CheckoutService $checkoutService,
    ) {}

    /**
     * POST /api/v1/transactions/checkout
     */
    public function checkout(CheckoutRequest $request): JsonResponse
    {
        $order = Order::where('order_code', $request->order_code)->first();

        if (!$order) {
            return response()->json(['message' => 'Order not found.'], 404);
        }

        if (in_array($order->status, ['completed', 'cancelled'])) {
            throw new OrderAlreadyProcessedException($order->order_code);
        }

        $order->load('orderItems.product', 'table');

        try {
            $transaction = $this->checkoutService->processCheckout($order, $request->validated());
        } catch (InvalidPaymentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (InsufficientStockException $e) {
            return response()->json(['message' => $e->getMessage(), 'items' => $e->getInsufficientItems()], 422);
        }

        $transaction->load('order.orderItems.product', 'order.table');

        return response()->json(['data' => new ReceiptResource($transaction)], 201);
    }

    /**
     * GET /api/v1/transactions/{id}/receipt
     */
    public function receipt(int $id): JsonResponse
    {
        $transaction = Transaction::with('order.orderItems.product', 'order.table')->find($id);

        if (!$transaction) {
            return response()->json(['message' => 'Transaction not found.'], 404);
        }

        return response()->json(['data' => new ReceiptResource($transaction)]);
    }
}
