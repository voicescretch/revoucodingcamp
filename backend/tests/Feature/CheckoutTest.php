<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\IncomeEntry;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Recipe;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Validates: Requirements 6.4, 6.5
 */
class CheckoutTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Helper: create a kasir user.
     */
    private function createKasir(): User
    {
        return User::factory()->create(['role' => 'kasir', 'is_active' => true]);
    }

    /**
     * Helper: create a category.
     */
    private function createCategory(string $type = 'product'): Category
    {
        return Category::create(['name' => 'Test Category ' . uniqid(), 'type' => $type]);
    }

    /**
     * Helper: create a raw material product.
     */
    private function createRawMaterial(Category $category, float $stock = 100): Product
    {
        return Product::create([
            'sku'          => 'RAW-' . uniqid(),
            'name'         => 'Raw Material ' . uniqid(),
            'category_id'  => $category->id,
            'unit'         => 'pcs',
            'buy_price'    => 1000,
            'sell_price'   => 0,
            'stock'        => $stock,
            'is_available' => false,
        ]);
    }

    /**
     * Helper: create a menu product.
     */
    private function createMenuProduct(Category $category): Product
    {
        return Product::create([
            'sku'          => 'MENU-' . uniqid(),
            'name'         => 'Menu Product ' . uniqid(),
            'category_id'  => $category->id,
            'unit'         => 'pcs',
            'buy_price'    => 5000,
            'sell_price'   => 8000,
            'stock'        => 0,
            'is_available' => true,
        ]);
    }

    /**
     * Helper: create a recipe linking menu product to raw material.
     */
    private function createRecipe(Product $menuProduct, Product $rawMaterial, float $qtyRequired = 10): Recipe
    {
        return Recipe::create([
            'menu_product_id'   => $menuProduct->id,
            'raw_material_id'   => $rawMaterial->id,
            'quantity_required' => $qtyRequired,
            'unit'              => 'pcs',
        ]);
    }

    /**
     * Helper: create a pending order with one order item.
     */
    private function createOrderWithItem(User $kasir, Product $menuProduct, int $qty = 1, float $subtotal = 8000): Order
    {
        $order = Order::create([
            'order_number' => 'ORD-' . now()->format('YmdHis') . '-' . rand(100, 999),
            'order_code'   => 'ORD-' . strtoupper(substr(uniqid(), -6)),
            'created_by'   => $kasir->id,
            'order_type'   => 'take_away',
            'status'       => 'pending',
        ]);

        OrderItem::create([
            'order_id'   => $order->id,
            'product_id' => $menuProduct->id,
            'quantity'   => $qty,
            'unit_price' => $subtotal / $qty,
            'subtotal'   => $subtotal,
        ]);

        return $order;
    }

    /**
     * Test 1: Successful checkout deducts stock, saves transaction,
     * completes order, and creates income entry.
     */
    public function test_successful_checkout_deducts_stock_saves_transaction_completes_order_creates_income_entry(): void
    {
        $kasir       = $this->createKasir();
        $category    = $this->createCategory();
        $rawMaterial = $this->createRawMaterial($category, 100);
        $menuProduct = $this->createMenuProduct($category);
        $this->createRecipe($menuProduct, $rawMaterial, 10);
        $order = $this->createOrderWithItem($kasir, $menuProduct, 1, 8000);

        $response = $this->actingAs($kasir, 'sanctum')
            ->postJson('/api/v1/transactions/checkout', [
                'order_code'     => $order->order_code,
                'payment_method' => 'cash',
                'paid_amount'    => 10000,
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.transaction_number', fn ($v) => !empty($v));

        // Stock deducted: 100 - (10 * 1) = 90
        $this->assertEquals(90, (float) $rawMaterial->fresh()->stock);

        // Transaction exists in DB
        $this->assertTrue(Transaction::where('order_id', $order->id)->exists());

        // Order status = completed
        $this->assertEquals('completed', $order->fresh()->status);

        // IncomeEntry linked to transaction
        $transaction = Transaction::where('order_id', $order->id)->first();
        $this->assertTrue(IncomeEntry::where('transaction_id', $transaction->id)->exists());
    }

    /**
     * Test 2: Checkout fails with insufficient stock and rolls back completely.
     */
    public function test_checkout_fails_with_insufficient_stock_rolls_back_completely(): void
    {
        $kasir       = $this->createKasir();
        $category    = $this->createCategory();
        $rawMaterial = $this->createRawMaterial($category, 5); // only 5 available
        $menuProduct = $this->createMenuProduct($category);
        $this->createRecipe($menuProduct, $rawMaterial, 10); // needs 10
        $order = $this->createOrderWithItem($kasir, $menuProduct, 1, 8000);

        $response = $this->actingAs($kasir, 'sanctum')
            ->postJson('/api/v1/transactions/checkout', [
                'order_code'     => $order->order_code,
                'payment_method' => 'cash',
                'paid_amount'    => 10000,
            ]);

        $response->assertStatus(422);

        // Stock unchanged
        $this->assertEquals(5, (float) $rawMaterial->fresh()->stock);

        // No transaction created
        $this->assertFalse(Transaction::where('order_id', $order->id)->exists());

        // Order still pending
        $this->assertEquals('pending', $order->fresh()->status);
    }

    /**
     * Test 3: Checkout fails when paid_amount is less than total.
     */
    public function test_checkout_fails_when_paid_amount_less_than_total(): void
    {
        $kasir       = $this->createKasir();
        $category    = $this->createCategory();
        $rawMaterial = $this->createRawMaterial($category, 100);
        $menuProduct = $this->createMenuProduct($category);
        $this->createRecipe($menuProduct, $rawMaterial, 10);
        $order = $this->createOrderWithItem($kasir, $menuProduct, 1, 8000);

        $response = $this->actingAs($kasir, 'sanctum')
            ->postJson('/api/v1/transactions/checkout', [
                'order_code'     => $order->order_code,
                'payment_method' => 'cash',
                'paid_amount'    => 5000, // less than 8000
            ]);

        $response->assertStatus(422);
    }

    /**
     * Test 4: Checkout of an already-completed order returns 409.
     */
    public function test_lookup_already_processed_order_returns_409(): void
    {
        $kasir    = $this->createKasir();
        $category = $this->createCategory();
        $menuProduct = $this->createMenuProduct($category);
        $order    = $this->createOrderWithItem($kasir, $menuProduct, 1, 8000);

        // Mark order as already completed
        $order->update(['status' => 'completed']);

        $response = $this->actingAs($kasir, 'sanctum')
            ->postJson('/api/v1/transactions/checkout', [
                'order_code'     => $order->order_code,
                'payment_method' => 'cash',
                'paid_amount'    => 10000,
            ]);

        $response->assertStatus(409);
    }
}
