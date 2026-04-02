<?php

namespace App\Http\Controllers\API;

use App\Exceptions\TableNotAvailableException;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Repositories\OrderRepository;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(
        private OrderService $orderService,
        private OrderRepository $orderRepository,
    ) {}

    /**
     * GET /api/v1/orders
     * Return active orders with optional ?status= filter.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = [];

        if ($request->has('status')) {
            $filters['status'] = $request->query('status');
        }

        $orders = $this->orderRepository->findActiveOrders($filters);
        $orders->load('orderItems.product');

        return response()->json([
            'data' => OrderResource::collection($orders),
        ]);
    }

    /**
     * POST /api/v1/orders
     * Handle both self_order (no auth) and cashier orders (auth).
     */
    public function store(Request $request): JsonResponse
    {
        $orderType = $request->input('order_type');

        // Base validation rules
        $rules = [
            'order_type'          => ['required', 'in:self_order,take_away,dine_in'],
            'items'               => ['required', 'array'],
            'items.*.product_id'  => ['required', 'exists:products,id'],
            'items.*.quantity'    => ['required', 'integer', 'min:1'],
        ];

        // Additional rules per order type
        if ($orderType === 'self_order') {
            $rules['table_identifier'] = ['required', 'string'];
        } elseif ($orderType === 'dine_in') {
            $rules['table_id'] = ['required', 'exists:tables,id'];
        }

        $validated = $request->validate($rules);

        try {
            if ($orderType === 'self_order') {
                $order = $this->orderService->createSelfOrder(
                    $validated['items'],
                    $validated['table_identifier']
                );
            } else {
                $tableId = $orderType === 'dine_in' ? ($validated['table_id'] ?? null) : null;
                $order   = $this->orderService->createCashierOrder(
                    $validated['items'],
                    $orderType,
                    $tableId
                );
            }

            $order->load('orderItems.product');

            return response()->json(['data' => new OrderResource($order)], 201);
        } catch (TableNotAvailableException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        } catch (\Exception $e) {
            if ($e->getCode() === 422) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            throw $e;
        }
    }

    /**
     * GET /api/v1/orders/{id}
     */
    public function show(int $id): JsonResponse
    {
        $order = $this->orderRepository->findWithItems($id);

        if ($order === null) {
            return response()->json(['message' => 'Order not found.'], 404);
        }

        return response()->json(['data' => new OrderResource($order)]);
    }

    /**
     * PUT /api/v1/orders/{id}/status
     */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'status'              => ['required', 'in:pending,confirmed,preparing,ready,completed,cancelled'],
            'cancellation_reason' => ['required_if:status,cancelled', 'nullable', 'string'],
        ]);

        $order = $this->orderRepository->findWithItems($id);

        if ($order === null) {
            return response()->json(['message' => 'Order not found.'], 404);
        }

        $order = $this->orderService->updateStatus(
            $order,
            $validated['status'],
            $validated['cancellation_reason'] ?? null
        );

        $order->load('orderItems.product');

        return response()->json(['data' => new OrderResource($order)]);
    }

    /**
     * GET /api/v1/orders/by-code/{code}
     */
    public function byCode(string $code): JsonResponse
    {
        $order = $this->orderRepository->findByCode($code);

        if ($order === null) {
            return response()->json(['message' => 'Order not found.'], 404);
        }

        $order->load('orderItems.product');

        return response()->json(['data' => new OrderResource($order)]);
    }
}
