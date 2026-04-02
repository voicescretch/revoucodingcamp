<?php

namespace App\Services;

use App\Models\Product;
use App\Models\StockMovement;
use App\Repositories\ProductRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class InventoryService
{
    public function __construct(private ProductRepository $productRepository) {}

    /**
     * Add stock to a product and record a StockMovement with type='in'.
     */
    public function addStock(Product $product, int|float $quantity, string $reference): StockMovement
    {
        return DB::transaction(function () use ($product, $quantity, $reference) {
            $stockBefore = $product->stock;

            $product->increment('stock', $quantity);
            $product->refresh();

            return StockMovement::create([
                'product_id'     => $product->id,
                'type'           => 'in',
                'quantity'       => $quantity,
                'stock_before'   => $stockBefore,
                'stock_after'    => $product->stock,
                'reference_type' => 'manual',
                'reference_id'   => null,
                'notes'          => $reference,
                'created_by'     => auth()->id(),
            ]);
        });
    }

    /**
     * Deduct stock from a product and record a StockMovement with type='out'.
     */
    public function deductStock(Product $product, int|float $quantity, string $refType, int $refId): StockMovement
    {
        return DB::transaction(function () use ($product, $quantity, $refType, $refId) {
            $stockBefore = $product->stock;

            $product->decrement('stock', $quantity);
            $product->refresh();

            return StockMovement::create([
                'product_id'     => $product->id,
                'type'           => 'out',
                'quantity'       => $quantity,
                'stock_before'   => $stockBefore,
                'stock_after'    => $product->stock,
                'reference_type' => $refType,
                'reference_id'   => $refId,
                'notes'          => null,
                'created_by'     => auth()->id(),
            ]);
        });
    }

    /**
     * Return all products where stock <= low_stock_threshold.
     */
    public function getLowStockItems(): Collection
    {
        return $this->productRepository->findLowStock();
    }

    /**
     * Return true if the product's current stock is sufficient for the required quantity.
     */
    public function checkStockSufficiency(int $productId, int|float $requiredQty): bool
    {
        $product = $this->productRepository->findById($productId);

        if ($product === null) {
            return false;
        }

        return $product->stock >= $requiredQty;
    }
}
