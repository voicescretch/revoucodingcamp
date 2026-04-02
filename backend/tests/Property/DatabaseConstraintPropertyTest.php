<?php

namespace Tests\Property;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Table;
use App\Models\User;
use Eris\Generators;
use Eris\TestTrait;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Property 24: Database Constraint Enforcement
 *
 * FK and unique constraint violations are rejected by the DB and the system
 * returns an appropriate error response.
 *
 * Unique constraints tested:
 *   - products.sku
 *   - tables.table_number
 *   - orders.order_code
 *
 * FK constraints tested:
 *   - products.category_id → categories.id
 *   - order_items.order_id → orders.id
 *
 * **Validates: Requirements 10.11**
 */
class DatabaseConstraintPropertyTest extends TestCase
{
    use DatabaseTransactions;
    use TestTrait;

    private User $manager;
    private string $managerToken;
    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = User::factory()->create([
            'role'      => 'head_manager',
            'is_active' => true,
        ]);
        $this->managerToken = $this->manager->createToken('test_token')->plainTextToken;

        $this->category = Category::create([
            'name' => 'Constraint Test Category ' . uniqid(),
            'type' => 'product',
        ]);
    }

    // -------------------------------------------------------------------------
    // Unique constraint: products.sku
    // -------------------------------------------------------------------------

    /**
     * Inserting a product with a duplicate SKU must be rejected (409 or 422),
     * and the original record must remain unchanged.
     *
     * **Validates: Requirements 10.11**
     */
    public function testDuplicateSkuUniqueConstraintIsEnforced(): void
    {
        $this->limitTo(5)->forAll(
            Generators::string()
        )->then(function (string $rawSku) {
            $sku = substr(preg_replace('/[^a-zA-Z0-9]/', '', $rawSku), 0, 40);
            if (empty($sku)) {
                $sku = 'SKU' . uniqid();
            }

            $payload = [
                'sku'        => $sku,
                'name'       => 'Product ' . $sku,
                'unit'       => 'pcs',
                'buy_price'  => 1000,
                'sell_price' => 1500,
                'category_id' => $this->category->id,
            ];

            // First insert must succeed
            $first = $this->withToken($this->managerToken)
                ->postJson('/api/v1/products', $payload);
            $first->assertStatus(201);

            // Duplicate insert must be rejected
            $second = $this->withToken($this->managerToken)
                ->postJson('/api/v1/products', $payload);

            $this->assertContains(
                $second->status(),
                [409, 422],
                "Duplicate SKU '{$sku}' must be rejected with 409 or 422, got {$second->status()}"
            );

            // Original data is unchanged — exactly one product with this SKU exists
            $count = Product::where('sku', $sku)->count();
            $this->assertEquals(
                1,
                $count,
                "Exactly one product with SKU '{$sku}' should exist after duplicate rejection"
            );

            // Cleanup
            $productId = $first->json('data.id');
            if ($productId) {
                $this->withToken($this->managerToken)->deleteJson("/api/v1/products/{$productId}");
            }
        });
    }

    /**
     * Directly inserting a duplicate SKU via Eloquent throws a QueryException.
     *
     * **Validates: Requirements 10.11**
     */
    public function testDuplicateSkuThrowsQueryException(): void
    {
        $this->limitTo(5)->forAll(
            Generators::string()
        )->then(function (string $rawSku) {
            $sku = substr(preg_replace('/[^a-zA-Z0-9]/', '', $rawSku), 0, 40);
            if (empty($sku)) {
                $sku = 'SKU' . uniqid();
            }

            $base = [
                'sku'                 => $sku,
                'name'                => 'Product ' . $sku,
                'unit'                => 'pcs',
                'buy_price'           => 1000,
                'sell_price'          => 1500,
                'stock'               => 0,
                'low_stock_threshold' => 0,
                'category_id'         => $this->category->id,
                'is_available'        => true,
            ];

            Product::create($base);

            $exceptionThrown = false;
            try {
                Product::create($base);
            } catch (QueryException $e) {
                $exceptionThrown = true;
            }

            $this->assertTrue(
                $exceptionThrown,
                "Inserting duplicate SKU '{$sku}' directly must throw a QueryException"
            );

            // No partial insert — still exactly one record
            $this->assertEquals(1, Product::where('sku', $sku)->count());
        });
    }

    // -------------------------------------------------------------------------
    // Unique constraint: tables.table_number
    // -------------------------------------------------------------------------

    /**
     * Inserting a table with a duplicate table_number must be rejected (409 or 422),
     * and the original record must remain unchanged.
     *
     * **Validates: Requirements 10.11**
     */
    public function testDuplicateTableNumberUniqueConstraintIsEnforced(): void
    {
        $this->limitTo(5)->forAll(
            Generators::string()
        )->then(function (string $rawNumber) {
            $tableNumber = substr(preg_replace('/[^a-zA-Z0-9]/', '', $rawNumber), 0, 20);
            if (empty($tableNumber)) {
                $tableNumber = 'T' . uniqid();
            }

            $payload = [
                'table_number' => $tableNumber,
                'name'         => 'Table ' . $tableNumber,
                'capacity'     => 4,
            ];

            // First insert must succeed
            $first = $this->withToken($this->managerToken)
                ->postJson('/api/v1/tables', $payload);
            $first->assertStatus(201);

            // Duplicate insert must be rejected
            $second = $this->withToken($this->managerToken)
                ->postJson('/api/v1/tables', $payload);

            $this->assertContains(
                $second->status(),
                [409, 422],
                "Duplicate table_number '{$tableNumber}' must be rejected with 409 or 422, got {$second->status()}"
            );

            // Original data is unchanged — exactly one table with this number exists
            $this->assertEquals(
                1,
                Table::where('table_number', $tableNumber)->count(),
                "Exactly one table with table_number '{$tableNumber}' should exist after duplicate rejection"
            );

            // Cleanup
            $tableId = $first->json('data.id');
            if ($tableId) {
                Table::find($tableId)?->delete();
            }
        });
    }

    /**
     * Directly inserting a duplicate table_number via Eloquent throws a QueryException.
     *
     * **Validates: Requirements 10.11**
     */
    public function testDuplicateTableNumberThrowsQueryException(): void
    {
        $this->limitTo(5)->forAll(
            Generators::string()
        )->then(function (string $rawNumber) {
            $tableNumber = substr(preg_replace('/[^a-zA-Z0-9]/', '', $rawNumber), 0, 20);
            if (empty($tableNumber)) {
                $tableNumber = 'T' . uniqid();
            }

            Table::create([
                'table_number' => $tableNumber,
                'name'         => 'Table ' . $tableNumber,
                'capacity'     => 4,
                'status'       => 'available',
            ]);

            $exceptionThrown = false;
            try {
                Table::create([
                    'table_number' => $tableNumber,
                    'name'         => 'Table Dup ' . $tableNumber,
                    'capacity'     => 2,
                    'status'       => 'available',
                ]);
            } catch (QueryException $e) {
                $exceptionThrown = true;
            }

            $this->assertTrue(
                $exceptionThrown,
                "Inserting duplicate table_number '{$tableNumber}' directly must throw a QueryException"
            );

            $this->assertEquals(1, Table::where('table_number', $tableNumber)->count());
        });
    }

    // -------------------------------------------------------------------------
    // Unique constraint: orders.order_code
    // -------------------------------------------------------------------------

    /**
     * Directly inserting a duplicate order_code via Eloquent throws a QueryException.
     *
     * **Validates: Requirements 10.11**
     */
    public function testDuplicateOrderCodeThrowsQueryException(): void
    {
        $this->limitTo(5)->forAll(
            Generators::string()
        )->then(function (string $rawCode) {
            $orderCode = substr(preg_replace('/[^a-zA-Z0-9]/', '', $rawCode), 0, 20);
            if (empty($orderCode)) {
                $orderCode = 'ORD' . uniqid();
            }

            $baseOrderData = [
                'order_number' => 'ON-' . uniqid(),
                'order_code'   => $orderCode,
                'created_by'   => $this->manager->id,
                'order_type'   => 'take_away',
                'status'       => 'pending',
            ];

            Order::create($baseOrderData);

            $exceptionThrown = false;
            try {
                Order::create(array_merge($baseOrderData, [
                    'order_number' => 'ON-' . uniqid(), // different order_number
                ]));
            } catch (QueryException $e) {
                $exceptionThrown = true;
            }

            $this->assertTrue(
                $exceptionThrown,
                "Inserting duplicate order_code '{$orderCode}' directly must throw a QueryException"
            );

            $this->assertEquals(1, Order::where('order_code', $orderCode)->count());
        });
    }

    // -------------------------------------------------------------------------
    // FK constraint: products.category_id → categories.id
    // -------------------------------------------------------------------------

    /**
     * Inserting a product with a non-existent category_id must be rejected (422 from
     * form validation, or 500/QueryException at DB level if bypassed).
     * Via the API, the form request validates category_id existence → 422.
     *
     * **Validates: Requirements 10.11**
     */
    public function testProductWithNonExistentCategoryIdIsRejectedByApi(): void
    {
        $this->limitTo(5)->forAll(
            Generators::choose(99000, 99999)
        )->then(function (int $nonExistentCategoryId) {
            // Ensure this category really does not exist
            Category::where('id', $nonExistentCategoryId)->delete();

            $payload = [
                'sku'         => 'SKU-FK-' . uniqid(),
                'name'        => 'FK Test Product',
                'unit'        => 'pcs',
                'buy_price'   => 1000,
                'sell_price'  => 1500,
                'category_id' => $nonExistentCategoryId,
            ];

            $response = $this->withToken($this->managerToken)
                ->postJson('/api/v1/products', $payload);

            // API validates category_id existence → 422 Unprocessable Entity
            $response->assertStatus(422);

            // No product was inserted
            $this->assertEquals(
                0,
                Product::where('sku', $payload['sku'])->count(),
                "No product should be inserted when category_id does not exist"
            );
        });
    }

    /**
     * Directly inserting a product with a non-existent category_id via Eloquent
     * throws a QueryException (FK violation at DB level).
     *
     * **Validates: Requirements 10.11**
     */
    public function testProductWithNonExistentCategoryIdThrowsQueryException(): void
    {
        $this->limitTo(5)->forAll(
            Generators::choose(99000, 99999)
        )->then(function (int $nonExistentCategoryId) {
            // Ensure this category really does not exist
            Category::where('id', $nonExistentCategoryId)->delete();

            $sku = 'SKU-FK-DIRECT-' . uniqid();

            $exceptionThrown = false;
            try {
                // Bypass form validation — insert directly
                Product::create([
                    'sku'                 => $sku,
                    'name'                => 'FK Direct Test',
                    'unit'                => 'pcs',
                    'buy_price'           => 1000,
                    'sell_price'          => 1500,
                    'stock'               => 0,
                    'low_stock_threshold' => 0,
                    'is_available'        => true,
                    'category_id'         => $nonExistentCategoryId,
                ]);
            } catch (QueryException $e) {
                $exceptionThrown = true;
            }

            $this->assertTrue(
                $exceptionThrown,
                "Inserting product with non-existent category_id {$nonExistentCategoryId} must throw a QueryException"
            );

            // No partial insert
            $this->assertEquals(0, Product::where('sku', $sku)->count());
        });
    }

    // -------------------------------------------------------------------------
    // FK constraint: order_items.order_id → orders.id
    // -------------------------------------------------------------------------

    /**
     * Directly inserting an order_item with a non-existent order_id via Eloquent
     * throws a QueryException (FK violation at DB level).
     *
     * **Validates: Requirements 10.11**
     */
    public function testOrderItemWithNonExistentOrderIdThrowsQueryException(): void
    {
        // Create a real product to satisfy the product_id FK
        $product = Product::create([
            'sku'                 => 'SKU-OI-FK-' . uniqid(),
            'name'                => 'OrderItem FK Test Product',
            'unit'                => 'pcs',
            'buy_price'           => 1000,
            'sell_price'          => 1500,
            'stock'               => 10,
            'low_stock_threshold' => 1,
            'category_id'         => $this->category->id,
            'is_available'        => true,
        ]);

        $this->limitTo(5)->forAll(
            Generators::choose(99000, 99999)
        )->then(function (int $nonExistentOrderId) use ($product) {
            // Ensure this order really does not exist
            Order::where('id', $nonExistentOrderId)->delete();

            $exceptionThrown = false;
            try {
                OrderItem::create([
                    'order_id'   => $nonExistentOrderId,
                    'product_id' => $product->id,
                    'quantity'   => 1,
                    'unit_price' => 1500,
                    'subtotal'   => 1500,
                ]);
            } catch (QueryException $e) {
                $exceptionThrown = true;
            }

            $this->assertTrue(
                $exceptionThrown,
                "Inserting order_item with non-existent order_id {$nonExistentOrderId} must throw a QueryException"
            );

            // No partial insert
            $this->assertEquals(
                0,
                OrderItem::where('order_id', $nonExistentOrderId)->count()
            );
        });

        // Cleanup product
        $product->delete();
    }
}
