<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateProductRequest;
use App\Http\Resources\ProductResource;
use App\Repositories\ProductRepository;
use App\Services\InventoryService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductController extends Controller
{
    public function __construct(
        private InventoryService $inventoryService,
        private ProductRepository $productRepository,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = \App\Models\Product::with('category');

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->integer('category_id'));
        }

        if ($request->has('is_available')) {
            $query->where('is_available', filter_var($request->input('is_available'), FILTER_VALIDATE_BOOLEAN));
        }

        return ProductResource::collection($query->get());
    }

    public function store(CreateProductRequest $request): JsonResponse
    {
        try {
            $product = $this->productRepository->create($request->validated());

            return response()->json(new ProductResource($product->load('category')), 201);
        } catch (UniqueConstraintViolationException) {
            return response()->json([
                'message' => 'SKU already exists.',
            ], 409);
        }
    }

    public function show(int $id): JsonResponse
    {
        $product = $this->productRepository->findById($id);

        if ($product === null) {
            return response()->json(['message' => 'Product not found.'], 404);
        }

        return response()->json(new ProductResource($product->load('category')));
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $product = $this->productRepository->findById($id);

        if ($product === null) {
            return response()->json(['message' => 'Product not found.'], 404);
        }

        $request->validate([
            'sku'                 => ['sometimes', 'string', 'unique:products,sku,' . $product->id],
            'name'                => ['sometimes', 'string'],
            'category_id'         => ['nullable', 'exists:categories,id'],
            'unit'                => ['sometimes', 'string'],
            'buy_price'           => ['sometimes', 'numeric', 'min:0'],
            'sell_price'          => ['sometimes', 'numeric', 'min:0'],
            'stock'               => ['nullable', 'numeric', 'min:0'],
            'low_stock_threshold' => ['nullable', 'numeric', 'min:0'],
            'is_available'        => ['nullable', 'boolean'],
            'description'         => ['nullable', 'string'],
            'image_path'          => ['nullable', 'string'],
        ]);

        $updated = $this->productRepository->update($product, $request->all());

        return response()->json(new ProductResource($updated->load('category')));
    }

    public function destroy(int $id): JsonResponse
    {
        $product = $this->productRepository->findById($id);

        if ($product === null) {
            return response()->json(['message' => 'Product not found.'], 404);
        }

        $this->productRepository->delete($product);

        return response()->json(null, 204);
    }

    public function lowStock(): AnonymousResourceCollection
    {
        $items = $this->inventoryService->getLowStockItems()->load('category');

        return ProductResource::collection($items);
    }
}
