<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Validates: Requirements 2.2, 2.3, 2.4, 2.5, 2.6, 2.9
 */
class InventoryTest extends TestCase
{
    use DatabaseTransactions;

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeManager(): User
    {
        return User::factory()->create([
            'role'      => 'head_manager',
            'is_active' => true,
        ]);
    }

    private function makeKasir(): User
    {
        return User::factory()->create([
            'role'      => 'kasir',
            'is_active' => true,
        ]);
    }

    private function makeCategory(): Category
    {
        return Category::create(['name' => 'Test Category', 'type' => 'product']);
    }

    private function makeProduct(Category $category, array $overrides = []): Product
    {
        return Product::create(array_merge([
            'sku'                 => 'SKU-' . uniqid(),
            'name'                => 'Test Product',
            'category_id'         => $category->id,
            'unit'                => 'pcs',
            'buy_price'           => 5000,
            'sell_price'          => 10000,
            'stock'               => 100,
            'low_stock_threshold' => 10,
            'is_available'        => true,
        ], $overrides));
    }

    private function productPayload(Category $category, array $overrides = []): array
    {
        return array_merge([
            'sku'                 => 'SKU-' . uniqid(),
            'name'                => 'New Product',
            'category_id'         => $category->id,
            'unit'                => 'pcs',
            'buy_price'           => 5000,
            'sell_price'          => 10000,
            'stock'               => 50,
            'low_stock_threshold' => 10,
            'is_available'        => true,
        ], $overrides);
    }

    // -------------------------------------------------------------------------
    // Requirement 2.2 & 2.3 — SKU duplikat → 409
    // -------------------------------------------------------------------------

    /**
     * Test: tambah produk dengan SKU duplikat mengembalikan error (422 dari validasi).
     *
     * CreateProductRequest memvalidasi unique:products,sku sehingga Laravel
     * mengembalikan 422 sebelum mencapai controller.
     */
    public function test_create_product_with_duplicate_sku_returns_error(): void
    {
        $manager  = $this->makeManager();
        $category = $this->makeCategory();

        $existingSku = 'SKU-DUPLICATE-001';
        $this->makeProduct($category, ['sku' => $existingSku]);

        $response = $this->actingAs($manager, 'sanctum')
            ->postJson('/api/v1/products', $this->productPayload($category, ['sku' => $existingSku]));

        // Validasi request mengembalikan 422 (unique rule di CreateProductRequest)
        $response->assertStatus(422);
        $response->assertJsonStructure(['message', 'errors' => ['sku']]);
    }

    /**
     * Test: tambah produk dengan SKU unik berhasil dengan status 201.
     */
    public function test_create_product_with_unique_sku_returns_201(): void
    {
        $manager  = $this->makeManager();
        $category = $this->makeCategory();

        $response = $this->actingAs($manager, 'sanctum')
            ->postJson('/api/v1/products', $this->productPayload($category));

        $response->assertStatus(201);
        $response->assertJsonStructure(['id', 'sku', 'name']);
    }

    // -------------------------------------------------------------------------
    // Requirement 2.4 & 2.9 — tambah stok → stock bertambah + movement tercatat
    // -------------------------------------------------------------------------

    /**
     * Test: tambah stok menambah jumlah stok produk dan mencatat stock_movement type='in'.
     */
    public function test_add_stock_increases_product_stock_and_records_movement(): void
    {
        $kasir    = $this->makeKasir();
        $category = $this->makeCategory();
        $product  = $this->makeProduct($category, ['stock' => 50]);

        $response = $this->actingAs($kasir, 'sanctum')
            ->postJson('/api/v1/stock-movements', [
                'product_id' => $product->id,
                'type'       => 'in',
                'quantity'   => 20,
                'notes'      => 'Restock dari supplier',
            ]);

        $response->assertStatus(201);

        // Stok produk harus bertambah
        $this->assertEquals(70, (float) $product->fresh()->stock);

        // Stock movement harus tercatat
        $this->assertDatabaseHas('stock_movements', [
            'product_id'   => $product->id,
            'type'         => 'in',
            'quantity'     => 20,
            'stock_before' => 50,
            'stock_after'  => 70,
        ]);
    }

    /**
     * Test: response tambah stok memiliki struktur yang benar.
     */
    public function test_add_stock_response_has_correct_structure(): void
    {
        $kasir    = $this->makeKasir();
        $category = $this->makeCategory();
        $product  = $this->makeProduct($category, ['stock' => 30]);

        $response = $this->actingAs($kasir, 'sanctum')
            ->postJson('/api/v1/stock-movements', [
                'product_id' => $product->id,
                'type'       => 'in',
                'quantity'   => 10,
            ]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'id', 'product_id', 'type', 'quantity', 'stock_before', 'stock_after',
        ]);
        $response->assertJsonPath('type', 'in');
    }

    // -------------------------------------------------------------------------
    // Requirement 2.5 & 2.9 — kurangi stok → stock berkurang + movement tercatat
    // -------------------------------------------------------------------------

    /**
     * Test: kurangi stok mengurangi jumlah stok produk dan mencatat stock_movement type='out'.
     */
    public function test_deduct_stock_decreases_product_stock_and_records_movement(): void
    {
        $kasir    = $this->makeKasir();
        $category = $this->makeCategory();
        $product  = $this->makeProduct($category, ['stock' => 100]);

        $response = $this->actingAs($kasir, 'sanctum')
            ->postJson('/api/v1/stock-movements', [
                'product_id' => $product->id,
                'type'       => 'out',
                'quantity'   => 30,
                'notes'      => 'Pemakaian harian',
            ]);

        $response->assertStatus(201);

        // Stok produk harus berkurang
        $this->assertEquals(70, (float) $product->fresh()->stock);

        // Stock movement harus tercatat
        $this->assertDatabaseHas('stock_movements', [
            'product_id'   => $product->id,
            'type'         => 'out',
            'quantity'     => 30,
            'stock_before' => 100,
            'stock_after'  => 70,
        ]);
    }

    /**
     * Test: response kurangi stok memiliki struktur yang benar.
     */
    public function test_deduct_stock_response_has_correct_structure(): void
    {
        $kasir    = $this->makeKasir();
        $category = $this->makeCategory();
        $product  = $this->makeProduct($category, ['stock' => 50]);

        $response = $this->actingAs($kasir, 'sanctum')
            ->postJson('/api/v1/stock-movements', [
                'product_id' => $product->id,
                'type'       => 'out',
                'quantity'   => 5,
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('type', 'out');
        $response->assertJsonStructure([
            'id', 'product_id', 'type', 'quantity', 'stock_before', 'stock_after',
        ]);
    }

    // -------------------------------------------------------------------------
    // Requirement 2.6 & 2.7 — produk dengan stock ≤ threshold muncul di low-stock
    // -------------------------------------------------------------------------

    /**
     * Test: produk dengan stock ≤ low_stock_threshold muncul di endpoint low-stock.
     */
    public function test_product_at_or_below_threshold_appears_in_low_stock_list(): void
    {
        $kasir    = $this->makeKasir();
        $category = $this->makeCategory();

        // Produk dengan stok tepat di threshold
        $atThreshold = $this->makeProduct($category, [
            'sku'                 => 'SKU-AT-THRESHOLD',
            'stock'               => 5,
            'low_stock_threshold' => 5,
        ]);

        // Produk dengan stok di bawah threshold
        $belowThreshold = $this->makeProduct($category, [
            'sku'                 => 'SKU-BELOW-THRESHOLD',
            'stock'               => 2,
            'low_stock_threshold' => 10,
        ]);

        // Produk dengan stok di atas threshold (tidak boleh muncul)
        $aboveThreshold = $this->makeProduct($category, [
            'sku'                 => 'SKU-ABOVE-THRESHOLD',
            'stock'               => 50,
            'low_stock_threshold' => 10,
        ]);

        $response = $this->actingAs($kasir, 'sanctum')
            ->getJson('/api/v1/products/low-stock');

        $response->assertStatus(200);

        $ids = collect($response->json('data'))->pluck('id')->toArray();

        $this->assertContains($atThreshold->id, $ids);
        $this->assertContains($belowThreshold->id, $ids);
        $this->assertNotContains($aboveThreshold->id, $ids);
    }

    /**
     * Test: head_manager juga dapat mengakses endpoint low-stock.
     */
    public function test_head_manager_can_access_low_stock_list(): void
    {
        $manager  = $this->makeManager();
        $category = $this->makeCategory();

        $this->makeProduct($category, [
            'sku'                 => 'SKU-LOW-MGR',
            'stock'               => 3,
            'low_stock_threshold' => 10,
        ]);

        $response = $this->actingAs($manager, 'sanctum')
            ->getJson('/api/v1/products/low-stock');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    /**
     * Test: produk dengan stok di atas threshold tidak muncul di low-stock list.
     */
    public function test_product_above_threshold_not_in_low_stock_list(): void
    {
        $kasir    = $this->makeKasir();
        $category = $this->makeCategory();

        $normalProduct = $this->makeProduct($category, [
            'sku'                 => 'SKU-NORMAL-STOCK',
            'stock'               => 100,
            'low_stock_threshold' => 10,
        ]);

        $response = $this->actingAs($kasir, 'sanctum')
            ->getJson('/api/v1/products/low-stock');

        $response->assertStatus(200);

        $ids = collect($response->json('data'))->pluck('id')->toArray();
        $this->assertNotContains($normalProduct->id, $ids);
    }
}
