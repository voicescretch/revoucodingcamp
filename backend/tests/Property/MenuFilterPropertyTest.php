<?php

namespace Tests\Property;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Eris\Generators;
use Eris\TestTrait;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Property 10: Filter Menu Berdasarkan Kategori
 *
 * For any category filter query, all returned menus SHALL have the matching category_id,
 * and NO menus from other categories shall appear.
 *
 * Validates: Requirements 3.5
 */
class MenuFilterPropertyTest extends TestCase
{
    use DatabaseTransactions;
    use TestTrait;

    private User $kasir;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->kasir = User::factory()->create([
            'role'      => 'kasir',
            'is_active' => true,
        ]);

        $this->token = $this->kasir->createToken('test-filter')->plainTextToken;
    }

    protected function tearDown(): void
    {
        $this->kasir->tokens()->delete();
        $this->kasir->delete();

        parent::tearDown();
    }

    /**
     * GET /api/v1/products?category_id=X returns ONLY products with that category_id.
     *
     * **Validates: Requirements 3.5**
     */
    public function testFilterByCategoryReturnsOnlyMatchingProducts(): void
    {
        $this->limitTo(5)->forAll(
            Generators::choose(1, 5),
            Generators::choose(1, 5)
        )->then(function (int $countA, int $countB) {
            $categoryA = Category::create([
                'name' => 'Cat A ' . uniqid(),
                'type' => 'product',
            ]);

            $categoryB = Category::create([
                'name' => 'Cat B ' . uniqid(),
                'type' => 'product',
            ]);

            $productsA = [];
            for ($i = 0; $i < $countA; $i++) {
                $productsA[] = Product::create([
                    'sku'          => 'SKU-A-' . uniqid(),
                    'name'         => 'Product A ' . uniqid(),
                    'unit'         => 'pcs',
                    'buy_price'    => 5000,
                    'sell_price'   => 10000,
                    'stock'        => 10,
                    'category_id'  => $categoryA->id,
                    'is_available' => true,
                ]);
            }

            $productsB = [];
            for ($i = 0; $i < $countB; $i++) {
                $productsB[] = Product::create([
                    'sku'          => 'SKU-B-' . uniqid(),
                    'name'         => 'Product B ' . uniqid(),
                    'unit'         => 'pcs',
                    'buy_price'    => 5000,
                    'sell_price'   => 10000,
                    'stock'        => 10,
                    'category_id'  => $categoryB->id,
                    'is_available' => true,
                ]);
            }

            // Filter by category A
            $response = $this->withToken($this->token)
                ->getJson('/api/v1/products?category_id=' . $categoryA->id);

            $response->assertStatus(200);

            $returnedCategoryIds = collect($response->json('data'))
                ->pluck('category_id')
                ->unique()
                ->toArray();

            // All returned products must belong to category A
            foreach ($returnedCategoryIds as $catId) {
                $this->assertEquals(
                    $categoryA->id,
                    $catId,
                    "All products returned must have category_id={$categoryA->id}, got {$catId}"
                );
            }

            // No product from category B should appear
            $returnedIds = collect($response->json('data'))->pluck('id')->toArray();
            foreach ($productsB as $productB) {
                $this->assertNotContains(
                    $productB->id,
                    $returnedIds,
                    "Product from category B (id={$productB->id}) must NOT appear in category A filter"
                );
            }

            // Cleanup
            foreach ($productsA as $p) {
                $p->delete();
            }
            foreach ($productsB as $p) {
                $p->delete();
            }
            $categoryA->delete();
            $categoryB->delete();
        });
    }

    /**
     * No products from other categories appear in the filtered result.
     *
     * **Validates: Requirements 3.5**
     */
    public function testFilterExcludesProductsFromOtherCategories(): void
    {
        $this->limitTo(5)->forAll(
            Generators::choose(1, 5)
        )->then(function (int $count) {
            $targetCategory = Category::create([
                'name' => 'Target Cat ' . uniqid(),
                'type' => 'product',
            ]);

            $otherCategory = Category::create([
                'name' => 'Other Cat ' . uniqid(),
                'type' => 'product',
            ]);

            $targetProducts = [];
            for ($i = 0; $i < $count; $i++) {
                $targetProducts[] = Product::create([
                    'sku'          => 'SKU-TGT-' . uniqid(),
                    'name'         => 'Target Product ' . uniqid(),
                    'unit'         => 'pcs',
                    'buy_price'    => 5000,
                    'sell_price'   => 10000,
                    'stock'        => 10,
                    'category_id'  => $targetCategory->id,
                    'is_available' => true,
                ]);
            }

            $otherProduct = Product::create([
                'sku'          => 'SKU-OTH-' . uniqid(),
                'name'         => 'Other Product ' . uniqid(),
                'unit'         => 'pcs',
                'buy_price'    => 5000,
                'sell_price'   => 10000,
                'stock'        => 10,
                'category_id'  => $otherCategory->id,
                'is_available' => true,
            ]);

            $response = $this->withToken($this->token)
                ->getJson('/api/v1/products?category_id=' . $targetCategory->id);

            $response->assertStatus(200);

            $returnedIds = collect($response->json('data'))->pluck('id')->toArray();

            // Other category product must not appear
            $this->assertNotContains(
                $otherProduct->id,
                $returnedIds,
                "Product from other category (id={$otherProduct->id}) must NOT appear in filtered result"
            );

            // All target products must appear
            foreach ($targetProducts as $tp) {
                $this->assertContains(
                    $tp->id,
                    $returnedIds,
                    "Target product (id={$tp->id}) must appear in filtered result"
                );
            }

            // Cleanup
            foreach ($targetProducts as $p) {
                $p->delete();
            }
            $otherProduct->delete();
            $targetCategory->delete();
            $otherCategory->delete();
        });
    }
}
