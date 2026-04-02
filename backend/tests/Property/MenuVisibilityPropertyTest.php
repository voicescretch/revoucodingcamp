<?php

namespace Tests\Property;

use App\Models\Category;
use App\Models\Product;
use App\Models\Table;
use Eris\Generators;
use Eris\TestTrait;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Property 8: Menu Visibility Berdasarkan Ketersediaan
 *
 * For any menu with is_available = false, it SHALL NOT appear in the public menu endpoint.
 * For any menu with is_available = true, it SHALL appear in the public menu endpoint.
 *
 * Validates: Requirements 3.2
 */
class MenuVisibilityPropertyTest extends TestCase
{
    use DatabaseTransactions;
    use TestTrait;

    private Category $category;
    private Table $table;

    protected function setUp(): void
    {
        parent::setUp();

        $this->category = Category::create([
            'name' => 'Test Category ' . uniqid(),
            'type' => 'product',
        ]);

        $this->table = Table::create([
            'table_number' => 'T-VIS-' . uniqid(),
            'name'         => 'Test Table Visibility',
            'qr_code'      => 'qr-vis-' . uniqid(),
            'capacity'     => 4,
            'status'       => 'available',
        ]);
    }

    /**
     * A product with is_available = false must NOT appear in GET /api/v1/tables/{table_number}/menu.
     *
     * **Validates: Requirements 3.2**
     */
    public function testUnavailableProductDoesNotAppearInMenu(): void
    {
        $this->limitTo(5)->forAll(
            Generators::elements([false])
        )->then(function (bool $isAvailable) {
            $product = Product::create([
                'sku'         => 'SKU-INVIS-' . uniqid(),
                'name'        => 'Unavailable Product ' . uniqid(),
                'unit'        => 'pcs',
                'buy_price'   => 5000,
                'sell_price'  => 10000,
                'stock'       => 10,
                'category_id' => $this->category->id,
                'is_available' => $isAvailable,
            ]);

            $response = $this->getJson('/api/v1/tables/' . $this->table->table_number . '/menu');

            $response->assertStatus(200);

            $menuIds = collect($response->json('data.menu'))->pluck('id')->toArray();

            $this->assertNotContains(
                $product->id,
                $menuIds,
                "Product with is_available=false must NOT appear in menu"
            );

            // Cleanup
            $product->delete();
        });
    }

    /**
     * A product with is_available = true must appear in GET /api/v1/tables/{table_number}/menu.
     *
     * **Validates: Requirements 3.2**
     */
    public function testAvailableProductAppearsInMenu(): void
    {
        $this->limitTo(5)->forAll(
            Generators::elements([true])
        )->then(function (bool $isAvailable) {
            $product = Product::create([
                'sku'          => 'SKU-VIS-' . uniqid(),
                'name'         => 'Available Product ' . uniqid(),
                'unit'         => 'pcs',
                'buy_price'    => 5000,
                'sell_price'   => 10000,
                'stock'        => 10,
                'category_id'  => $this->category->id,
                'is_available' => $isAvailable,
            ]);

            $response = $this->getJson('/api/v1/tables/' . $this->table->table_number . '/menu');

            $response->assertStatus(200);

            $menuIds = collect($response->json('data.menu'))->pluck('id')->toArray();

            $this->assertContains(
                $product->id,
                $menuIds,
                "Product with is_available=true must appear in menu"
            );

            // Cleanup
            $product->delete();
        });
    }

    /**
     * Menu visibility is consistent regardless of is_available value.
     *
     * **Validates: Requirements 3.2**
     */
    public function testMenuVisibilityMatchesIsAvailableFlag(): void
    {
        $this->limitTo(5)->forAll(
            Generators::elements([true, false])
        )->then(function (bool $isAvailable) {
            $product = Product::create([
                'sku'          => 'SKU-FLAG-' . uniqid(),
                'name'         => 'Flag Product ' . uniqid(),
                'unit'         => 'pcs',
                'buy_price'    => 5000,
                'sell_price'   => 10000,
                'stock'        => 10,
                'category_id'  => $this->category->id,
                'is_available' => $isAvailable,
            ]);

            $response = $this->getJson('/api/v1/tables/' . $this->table->table_number . '/menu');

            $response->assertStatus(200);

            $menuIds = collect($response->json('data.menu'))->pluck('id')->toArray();

            if ($isAvailable) {
                $this->assertContains(
                    $product->id,
                    $menuIds,
                    "Product with is_available=true must appear in menu"
                );
            } else {
                $this->assertNotContains(
                    $product->id,
                    $menuIds,
                    "Product with is_available=false must NOT appear in menu"
                );
            }

            // Cleanup
            $product->delete();
        });
    }
}
