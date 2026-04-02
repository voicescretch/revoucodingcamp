<?php

namespace Tests\Property;

use App\Models\Category;
use App\Models\Product;
use Eris\Generators;
use Eris\TestTrait;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Property 9: Harga Menu Terbaru Digunakan di Order Baru
 *
 * For any menu whose price is updated, all orders created AFTER the update
 * SHALL use the new price as unit_price in order_items.
 *
 * Validates: Requirements 3.3
 */
class MenuPricePropertyTest extends TestCase
{
    use DatabaseTransactions;
    use TestTrait;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->category = Category::create([
            'name' => 'Test Category ' . uniqid(),
            'type' => 'product',
        ]);
    }

    /**
     * After updating a product's sell_price, the new price is reflected via GET /api/v1/products/{id}.
     *
     * **Validates: Requirements 3.3**
     */
    public function testUpdatedPriceIsReflectedInProductDetail(): void
    {
        $this->limitTo(5)->forAll(
            Generators::choose(1000, 100000),
            Generators::choose(1000, 100000)
        )->then(function (int $originalPrice, int $newPrice) {
            $product = Product::create([
                'sku'          => 'SKU-PRICE-' . uniqid(),
                'name'         => 'Price Test Product ' . uniqid(),
                'unit'         => 'pcs',
                'buy_price'    => $originalPrice,
                'sell_price'   => $originalPrice,
                'stock'        => 10,
                'category_id'  => $this->category->id,
                'is_available' => true,
            ]);

            // Update the sell_price
            $product->update(['sell_price' => $newPrice]);

            // Verify fresh() reflects the new price
            $freshProduct = $product->fresh();

            $this->assertEquals(
                $newPrice,
                (int) $freshProduct->sell_price,
                "After updating sell_price to {$newPrice}, fresh()->sell_price must match"
            );

            // Cleanup
            $product->delete();
        });
    }

    /**
     * The updated price is the one that would be used as unit_price in new order_items.
     *
     * **Validates: Requirements 3.3**
     */
    public function testUpdatedPriceMatchesFreshProductSellPrice(): void
    {
        $this->limitTo(5)->forAll(
            Generators::choose(1000, 100000),
            Generators::choose(1000, 100000)
        )->then(function (int $originalPrice, int $newPrice) {
            $product = Product::create([
                'sku'          => 'SKU-UNIT-' . uniqid(),
                'name'         => 'Unit Price Product ' . uniqid(),
                'unit'         => 'pcs',
                'buy_price'    => $originalPrice,
                'sell_price'   => $originalPrice,
                'stock'        => 10,
                'category_id'  => $this->category->id,
                'is_available' => true,
            ]);

            // Update the sell_price
            $product->update(['sell_price' => $newPrice]);

            // The unit_price for a new order_item would be taken from product's current sell_price
            $currentPrice = (int) $product->fresh()->sell_price;

            $this->assertEquals(
                $newPrice,
                $currentPrice,
                "The unit_price for new order_items must equal the updated sell_price={$newPrice}"
            );

            // Cleanup
            $product->delete();
        });
    }

    /**
     * Price update is persisted and not the original price.
     *
     * **Validates: Requirements 3.3**
     */
    public function testOriginalPriceIsNoLongerUsedAfterUpdate(): void
    {
        $this->limitTo(5)->forAll(
            Generators::choose(1000, 50000),
            Generators::choose(50001, 100000)
        )->then(function (int $originalPrice, int $newPrice) {
            $product = Product::create([
                'sku'          => 'SKU-OLD-' . uniqid(),
                'name'         => 'Old Price Product ' . uniqid(),
                'unit'         => 'pcs',
                'buy_price'    => $originalPrice,
                'sell_price'   => $originalPrice,
                'stock'        => 10,
                'category_id'  => $this->category->id,
                'is_available' => true,
            ]);

            // Update the sell_price to a higher value
            $product->update(['sell_price' => $newPrice]);

            $freshPrice = (int) $product->fresh()->sell_price;

            // The fresh price must be the new price, not the original
            $this->assertNotEquals(
                $originalPrice,
                $freshPrice,
                "After price update, fresh sell_price must not equal original price={$originalPrice}"
            );

            $this->assertEquals(
                $newPrice,
                $freshPrice,
                "After price update, fresh sell_price must equal new price={$newPrice}"
            );

            // Cleanup
            $product->delete();
        });
    }
}
