<?php

namespace Tests\Property;

use App\Exceptions\InsufficientStockException;
use App\Models\Category;
use App\Models\IncomeEntry;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Recipe;
use App\Models\StockMovement;
use App\Models\Transaction;
use App\Models\User;
use App\Services\CheckoutService;
use Eris\Generators;
use Eris\TestTrait;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Property 18: Atomisitas Checkout
 *
 * All 3 conditions (stock deducted, transaction saved, order completed) happen
 * together or not at all.
 *
 * Validates: Requirements 6.4, 6.5
 */
class CheckoutAtomicityPropertyTest extends TestCase
{
    use DatabaseTransactions;
    use TestTrait;

    private CheckoutService $checkoutService;
    private User $kasir;
    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->checkoutService = app(CheckoutService::class);
        $this->category = Category::create(['name' => 'Cat-P18-' . uniqid(), 'type' => 'product']);
        $this->kasir = User::factory()->create(['role' => 'kasir', 'is_active' => true]);
        $this->actingAs($this->kasir, 'sanctum');
    }

    /**
     * Successful checkout: stock deducted + transaction saved + order completed + income_entry created.
     *
     * **Validates: Requirements 6.4**
     */
    public function testSuccessfulCheckoutIsAtomic(): void
    {
        $this->limitTo(5)->forAll(
            Generators::choose(1, 3)  // qty
        )->then(function (int $qty) {
            $rawMaterial = Product::create([
                'sku'                 => 'RAW-P18-' . uniqid(),
                'name'                => 'Raw Material P18',
                'unit'                => 'ml',
                'buy_price'           => 1000,
                'sell_price'          => 1000,
                'stock'               => 100,
                'low_stock_threshold' => 5,
                'category_id'         => $this->category->id,
                'is_available'        => true,
            ]);

            $menuProduct = Product::create([
                'sku'                 => 'MENU-P18-' . uniqid(),
                'name'                => 'Menu P18',
                'unit'                => 'pcs',
                'buy_price'           => 5000,
                'sell_price'          => 8000,
                'stock'               => 50,
                'low_stock_threshold' => 2,
                'category_id'         => $this->category->id,
                'is_available'        => true,
            ]);

            $recipe = Recipe::create([
                'menu_product_id'  => $menuProduct->id,
                'raw_material_id'  => $rawMaterial->id,
                'quantity_required' => 10,
                'unit'             => 'ml',
            ]);

            $stockBefore = $rawMaterial->stock;

            $order = Order::create([
                'order_number' => 'ORD-P18-' . uniqid(),
                'order_code'   => 'P18-' . uniqid(),
                'created_by'   => $this->kasir->id,
                'order_type'   => 'take_away',
                'status'       => 'pending',
            ]);

            OrderItem::create([
                'order_id'   => $order->id,
                'product_id' => $menuProduct->id,
                'quantity'   => $qty,
                'unit_price' => 8000,
                'subtotal'   => 8000 * $qty,
            ]);

            $order->load('orderItems');

            $transaction = $this->checkoutService->processCheckout($order, [
                'payment_method' => 'cash',
                'paid_amount'    => 8000 * $qty + 1000,
            ]);

            // (a) Stock deducted
            $rawMaterial->refresh();
            $expectedStock = $stockBefore - (10 * $qty);
            $this->assertEquals($expectedStock, $rawMaterial->stock,
                "Raw material stock must be deducted by recipe qty * order qty");

            // (b) Transaction saved
            $this->assertNotNull(Transaction::find($transaction->id),
                "Transaction must be saved in DB");

            // (c) Order completed
            $order->refresh();
            $this->assertEquals('completed', $order->status,
                "Order status must be 'completed' after checkout");

            // (d) Income entry created
            $incomeEntry = IncomeEntry::where('transaction_id', $transaction->id)->first();
            $this->assertNotNull($incomeEntry, "IncomeEntry must be created after checkout");

            // Cleanup
            $incomeEntry?->delete();
            StockMovement::where('reference_id', $transaction->id)->delete();
            $transaction->delete();
            $order->orderItems()->delete();
            $order->delete();
            $recipe->delete();
            $menuProduct->delete();
            $rawMaterial->delete();
        });
    }

    /**
     * Failed checkout (insufficient stock): no transaction, no stock change, order stays pending.
     *
     * **Validates: Requirements 6.5**
     */
    public function testFailedCheckoutRollsBackCompletely(): void
    {
        $this->limitTo(5)->forAll(
            Generators::choose(1, 3)  // qty to order
        )->then(function (int $qty) {
            $rawMaterial = Product::create([
                'sku'                 => 'RAW-P18F-' . uniqid(),
                'name'                => 'Raw Material P18F',
                'unit'                => 'ml',
                'buy_price'           => 1000,
                'sell_price'          => 1000,
                'stock'               => 5,  // only 5 available
                'low_stock_threshold' => 1,
                'category_id'         => $this->category->id,
                'is_available'        => true,
            ]);

            $menuProduct = Product::create([
                'sku'                 => 'MENU-P18F-' . uniqid(),
                'name'                => 'Menu P18F',
                'unit'                => 'pcs',
                'buy_price'           => 5000,
                'sell_price'          => 8000,
                'stock'               => 50,
                'low_stock_threshold' => 2,
                'category_id'         => $this->category->id,
                'is_available'        => true,
            ]);

            // Recipe requires 10ml per unit, but only 5ml available
            $recipe = Recipe::create([
                'menu_product_id'  => $menuProduct->id,
                'raw_material_id'  => $rawMaterial->id,
                'quantity_required' => 10,
                'unit'             => 'ml',
            ]);

            $stockBefore = $rawMaterial->stock;
            $transactionCountBefore = Transaction::count();

            $order = Order::create([
                'order_number' => 'ORD-P18F-' . uniqid(),
                'order_code'   => 'P18F-' . uniqid(),
                'created_by'   => $this->kasir->id,
                'order_type'   => 'take_away',
                'status'       => 'pending',
            ]);

            OrderItem::create([
                'order_id'   => $order->id,
                'product_id' => $menuProduct->id,
                'quantity'   => $qty,
                'unit_price' => 8000,
                'subtotal'   => 8000 * $qty,
            ]);

            $order->load('orderItems');

            $exceptionThrown = false;
            try {
                $this->checkoutService->processCheckout($order, [
                    'payment_method' => 'cash',
                    'paid_amount'    => 999999,
                ]);
            } catch (InsufficientStockException $e) {
                $exceptionThrown = true;
            }

            $this->assertTrue($exceptionThrown, "InsufficientStockException must be thrown");

            // Stock unchanged
            $rawMaterial->refresh();
            $this->assertEquals($stockBefore, $rawMaterial->stock,
                "Stock must not change on failed checkout");

            // No new transaction
            $this->assertEquals($transactionCountBefore, Transaction::count(),
                "No transaction must be created on failed checkout");

            // Order still pending
            $order->refresh();
            $this->assertEquals('pending', $order->status,
                "Order must remain 'pending' on failed checkout");

            // Cleanup
            $order->orderItems()->delete();
            $order->delete();
            $recipe->delete();
            $menuProduct->delete();
            $rawMaterial->delete();
        });
    }
}
