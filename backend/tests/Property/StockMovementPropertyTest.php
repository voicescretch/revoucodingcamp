<?php

namespace Tests\Property;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Repositories\ProductRepository;
use App\Services\InventoryService;
use Eris\Generators;
use Eris\TestTrait;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Property 6: Stock Movement Round-Trip
 *
 * For any stock item and any movement quantity (in or out):
 * (a) stock value changes by the movement quantity
 * (b) a new StockMovement record exists with consistent stock_before, stock_after, quantity
 *     (stock_after = stock_before ± quantity)
 *
 * Validates: Requirements 2.4, 2.5, 2.9
 */
class StockMovementPropertyTest extends TestCase
{
    use DatabaseTransactions;
    use TestTrait;

    private InventoryService $inventoryService;
    private Category $category;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->inventoryService = app(InventoryService::class);

        $this->category = Category::create([
            'name' => 'Test Category ' . uniqid(),
            'type' => 'product',
        ]);

        // InventoryService uses auth()->id() for created_by — create and act as a user
        $this->user = User::factory()->create([
            'role'      => 'kasir',
            'is_active' => true,
        ]);
        $this->actingAs($this->user, 'sanctum');
    }

    /**
     * For any add quantity, stock_after = stock_before + quantity and the movement record is consistent.
     *
     * **Validates: Requirements 2.4, 2.9**
     */
    public function testAddStockRoundTrip(): void
    {
        $this->limitTo(5)->forAll(
            Generators::choose(1, 100),
            Generators::choose(0, 100)
        )->then(function (int $addQty, int $initialStock) {
            $product = Product::create([
                'sku'                 => 'SKU-ADD-' . uniqid(),
                'name'                => 'Test Product Add ' . uniqid(),
                'unit'                => 'pcs',
                'buy_price'           => 1000,
                'sell_price'          => 1500,
                'stock'               => $initialStock,
                'low_stock_threshold' => 5,
                'category_id'         => $this->category->id,
                'is_available'        => true,
            ]);

            $stockBefore = (float) $product->stock;

            $movement = $this->inventoryService->addStock($product, $addQty, 'test_reference');

            // (a) movement record is consistent: stock_after = stock_before + quantity
            $this->assertEquals(
                (float) $movement->stock_before + (float) $movement->quantity,
                (float) $movement->stock_after,
                "For 'in' movement: stock_after must equal stock_before + quantity"
            );

            // (b) stock_before in movement matches the product's stock before the operation
            $this->assertEquals(
                $stockBefore,
                (float) $movement->stock_before,
                "movement->stock_before must match product stock before addStock"
            );

            // (c) product's current stock matches movement->stock_after
            $freshProduct = $product->fresh();
            $this->assertEquals(
                (float) $movement->stock_after,
                (float) $freshProduct->stock,
                "product->fresh()->stock must equal movement->stock_after"
            );

            // (d) movement type is 'in'
            $this->assertEquals('in', $movement->type);

            // Cleanup
            $product->stockMovements()->delete();
            $product->delete();
        });
    }

    /**
     * For any deduct quantity, stock_after = stock_before - quantity and the movement record is consistent.
     *
     * **Validates: Requirements 2.5, 2.9**
     */
    public function testDeductStockRoundTrip(): void
    {
        $this->limitTo(5)->forAll(
            Generators::choose(1, 50)
        )->then(function (int $deductQty) {
            // Ensure initial stock is always >= deductQty to avoid negative stock
            $initialStock = $deductQty + 50;

            $product = Product::create([
                'sku'                 => 'SKU-DED-' . uniqid(),
                'name'                => 'Test Product Deduct ' . uniqid(),
                'unit'                => 'pcs',
                'buy_price'           => 1000,
                'sell_price'          => 1500,
                'stock'               => $initialStock,
                'low_stock_threshold' => 5,
                'category_id'         => $this->category->id,
                'is_available'        => true,
            ]);

            $stockBefore = (float) $product->stock;

            $movement = $this->inventoryService->deductStock($product, $deductQty, 'manual', 0);

            // (a) movement record is consistent: stock_after = stock_before - quantity
            $this->assertEquals(
                (float) $movement->stock_before - (float) $movement->quantity,
                (float) $movement->stock_after,
                "For 'out' movement: stock_after must equal stock_before - quantity"
            );

            // (b) stock_before in movement matches the product's stock before the operation
            $this->assertEquals(
                $stockBefore,
                (float) $movement->stock_before,
                "movement->stock_before must match product stock before deductStock"
            );

            // (c) product's current stock matches movement->stock_after
            $freshProduct = $product->fresh();
            $this->assertEquals(
                (float) $movement->stock_after,
                (float) $freshProduct->stock,
                "product->fresh()->stock must equal movement->stock_after"
            );

            // (d) movement type is 'out'
            $this->assertEquals('out', $movement->type);

            // Cleanup
            $product->stockMovements()->delete();
            $product->delete();
        });
    }

    /**
     * For any sequence of add then deduct, the final stock is consistent with all movements.
     *
     * **Validates: Requirements 2.4, 2.5, 2.9**
     */
    public function testAddThenDeductRoundTrip(): void
    {
        $this->limitTo(5)->forAll(
            Generators::choose(1, 100),
            Generators::choose(1, 50)
        )->then(function (int $addQty, int $deductQty) {
            $initialStock = 10;

            $product = Product::create([
                'sku'                 => 'SKU-SEQ-' . uniqid(),
                'name'                => 'Test Product Seq ' . uniqid(),
                'unit'                => 'pcs',
                'buy_price'           => 1000,
                'sell_price'          => 1500,
                'stock'               => $initialStock,
                'low_stock_threshold' => 5,
                'category_id'         => $this->category->id,
                'is_available'        => true,
            ]);

            $addMovement = $this->inventoryService->addStock($product, $addQty, 'test_add');

            // Refresh product after add
            $product->refresh();

            $deductMovement = $this->inventoryService->deductStock($product, $deductQty, 'manual', 0);

            // Verify add movement consistency
            $this->assertEquals(
                (float) $addMovement->stock_before + (float) $addMovement->quantity,
                (float) $addMovement->stock_after,
                "Add movement: stock_after = stock_before + quantity"
            );

            // Verify deduct movement consistency
            $this->assertEquals(
                (float) $deductMovement->stock_before - (float) $deductMovement->quantity,
                (float) $deductMovement->stock_after,
                "Deduct movement: stock_after = stock_before - quantity"
            );

            // Final stock = initialStock + addQty - deductQty
            $expectedFinalStock = $initialStock + $addQty - $deductQty;
            $this->assertEquals(
                (float) $expectedFinalStock,
                (float) $product->fresh()->stock,
                "Final stock must equal initialStock + addQty - deductQty"
            );

            // Cleanup
            $product->stockMovements()->delete();
            $product->delete();
        });
    }
}
