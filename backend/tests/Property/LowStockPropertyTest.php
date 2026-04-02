<?php

namespace Tests\Property;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Services\InventoryService;
use Eris\Generators;
use Eris\TestTrait;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Property 7: Low Stock Threshold Detection
 *
 * For any stock item with a set low_stock_threshold:
 * - if stock <= threshold → item SHALL appear in getLowStockItems()
 * - if stock > threshold  → item SHALL NOT appear in getLowStockItems()
 *
 * Validates: Requirements 2.6, 2.7, 2.8
 */
class LowStockPropertyTest extends TestCase
{
    use DatabaseTransactions;
    use TestTrait;

    private InventoryService $inventoryService;
    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->inventoryService = app(InventoryService::class);

        $this->category = Category::create([
            'name' => 'Test Category ' . uniqid(),
            'type' => 'product',
        ]);
    }

    /**
     * A product with stock <= threshold must appear in getLowStockItems().
     *
     * **Validates: Requirements 2.6, 2.7**
     */
    public function testProductAppearsInLowStockWhenStockAtOrBelowThreshold(): void
    {
        $this->limitTo(5)->forAll(
            Generators::choose(0, 50),
            Generators::choose(0, 50)
        )->then(function (int $stock, int $threshold) {
            // Ensure stock <= threshold for this test case
            $actualStock     = min($stock, $threshold);
            $actualThreshold = max($stock, $threshold);

            $product = Product::create([
                'sku'                 => 'SKU-LOW-' . uniqid(),
                'name'                => 'Low Stock Product ' . uniqid(),
                'unit'                => 'pcs',
                'buy_price'           => 1000,
                'sell_price'          => 1500,
                'stock'               => $actualStock,
                'low_stock_threshold' => $actualThreshold,
                'category_id'         => $this->category->id,
                'is_available'        => true,
            ]);

            $lowStockItems = $this->inventoryService->getLowStockItems();
            $ids           = $lowStockItems->pluck('id')->toArray();

            $this->assertContains(
                $product->id,
                $ids,
                "Product with stock={$actualStock} and threshold={$actualThreshold} must appear in low-stock list"
            );

            // Cleanup
            $product->delete();
        });
    }

    /**
     * A product with stock > threshold must NOT appear in getLowStockItems().
     *
     * **Validates: Requirements 2.8**
     */
    public function testProductDoesNotAppearInLowStockWhenStockAboveThreshold(): void
    {
        $this->limitTo(5)->forAll(
            Generators::choose(1, 50),
            Generators::choose(0, 49)
        )->then(function (int $stockExtra, int $threshold) {
            // stock = threshold + stockExtra, so stock > threshold always
            $stock = $threshold + $stockExtra;

            $product = Product::create([
                'sku'                 => 'SKU-OK-' . uniqid(),
                'name'                => 'Sufficient Stock Product ' . uniqid(),
                'unit'                => 'pcs',
                'buy_price'           => 1000,
                'sell_price'          => 1500,
                'stock'               => $stock,
                'low_stock_threshold' => $threshold,
                'category_id'         => $this->category->id,
                'is_available'        => true,
            ]);

            $lowStockItems = $this->inventoryService->getLowStockItems();
            $ids           = $lowStockItems->pluck('id')->toArray();

            $this->assertNotContains(
                $product->id,
                $ids,
                "Product with stock={$stock} and threshold={$threshold} must NOT appear in low-stock list"
            );

            // Cleanup
            $product->delete();
        });
    }

    /**
     * A product at exactly stock == threshold must appear in getLowStockItems() (boundary case).
     *
     * **Validates: Requirements 2.6, 2.7**
     */
    public function testProductAtExactThresholdAppearsInLowStock(): void
    {
        $this->limitTo(5)->forAll(
            Generators::choose(0, 50)
        )->then(function (int $threshold) {
            // stock == threshold exactly
            $product = Product::create([
                'sku'                 => 'SKU-EQ-' . uniqid(),
                'name'                => 'Exact Threshold Product ' . uniqid(),
                'unit'                => 'pcs',
                'buy_price'           => 1000,
                'sell_price'          => 1500,
                'stock'               => $threshold,
                'low_stock_threshold' => $threshold,
                'category_id'         => $this->category->id,
                'is_available'        => true,
            ]);

            $lowStockItems = $this->inventoryService->getLowStockItems();
            $ids           = $lowStockItems->pluck('id')->toArray();

            $this->assertContains(
                $product->id,
                $ids,
                "Product with stock == threshold ({$threshold}) must appear in low-stock list"
            );

            // Cleanup
            $product->delete();
        });
    }
}
