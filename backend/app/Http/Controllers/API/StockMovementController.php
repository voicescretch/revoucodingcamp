<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\StockMovementResource;
use App\Models\StockMovement;
use App\Repositories\ProductRepository;
use App\Services\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class StockMovementController extends Controller
{
    public function __construct(
        private InventoryService $inventoryService,
        private ProductRepository $productRepository,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'type'       => ['required', 'in:in,out'],
            'quantity'   => ['required', 'numeric', 'min:0.0001'],
            'notes'      => ['nullable', 'string'],
        ]);

        $product = $this->productRepository->findById($data['product_id']);

        if ($data['type'] === 'in') {
            $movement = $this->inventoryService->addStock($product, $data['quantity'], $data['notes'] ?? '');
        } else {
            $movement = $this->inventoryService->deductStock($product, $data['quantity'], 'manual', 0);

            // Attach notes if provided (deductStock doesn't accept notes directly)
            if (!empty($data['notes'])) {
                $movement->update(['notes' => $data['notes']]);
            }
        }

        return response()->json(new StockMovementResource($movement->load('product')), 201);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = StockMovement::with('product');

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->integer('product_id'));
        }

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        return StockMovementResource::collection($query->latest()->get());
    }
}
