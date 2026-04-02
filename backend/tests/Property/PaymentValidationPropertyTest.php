<?php

namespace Tests\Property;

use App\Exceptions\InvalidPaymentException;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Services\CheckoutService;
use Eris\Generators;
use Eris\TestTrait;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Property 17: Validasi Jumlah Pembayaran
 *
 * For any checkout, if paid_amount < total_amount, checkout SHALL be rejected.
 *
 * Validates: Requirements 6.3
 */
class PaymentValidationPropertyTest extends TestCase
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
        $this->category = Category::create(['name' => 'Cat-' . uniqid(), 'type' => 'product']);
        $this->kasir = User::factory()->create(['role' => 'kasir', 'is_active' => true]);
        $this->actingAs($this->kasir, 'sanctum');
    }

    /**
     * Checkout is rejected when paid_amount < total_amount.
     *
     * **Validates: Requirements 6.3**
     */
    public function testCheckoutRejectedWhenPaidAmountLessThanTotal(): void
    {
        $this->limitTo(5)->forAll(
            Generators::choose(1000, 50000),  // total_amount
            Generators::choose(1, 999)         // shortfall
        )->then(function (int $totalAmount, int $shortfall) {
            $paidAmount = $totalAmount - $shortfall; // always less than total

            $product = Product::create([
                'sku'                 => 'SKU-P17-' . uniqid(),
                'name'                => 'Product P17',
                'unit'                => 'pcs',
                'buy_price'           => $totalAmount,
                'sell_price'          => $totalAmount,
                'stock'               => 100,
                'low_stock_threshold' => 5,
                'category_id'         => $this->category->id,
                'is_available'        => true,
            ]);

            $order = Order::create([
                'order_number' => 'ORD-P17-' . uniqid(),
                'order_code'   => 'P17-' . uniqid(),
                'created_by'   => $this->kasir->id,
                'order_type'   => 'take_away',
                'status'       => 'pending',
            ]);

            OrderItem::create([
                'order_id'   => $order->id,
                'product_id' => $product->id,
                'quantity'   => 1,
                'unit_price' => $totalAmount,
                'subtotal'   => $totalAmount,
            ]);

            $order->load('orderItems');

            $exceptionThrown = false;
            try {
                $this->checkoutService->processCheckout($order, [
                    'payment_method' => 'cash',
                    'paid_amount'    => $paidAmount,
                ]);
            } catch (InvalidPaymentException $e) {
                $exceptionThrown = true;
                $this->assertEquals(422, $e->getCode());
            }

            $this->assertTrue($exceptionThrown,
                "InvalidPaymentException must be thrown when paid ({$paidAmount}) < total ({$totalAmount})");

            // Cleanup
            $order->orderItems()->delete();
            $order->delete();
            $product->delete();
        });
    }

    /**
     * Checkout succeeds when paid_amount >= total_amount.
     *
     * **Validates: Requirements 6.3**
     */
    public function testCheckoutSucceedsWhenPaidAmountSufficient(): void
    {
        $this->limitTo(5)->forAll(
            Generators::choose(1000, 10000),  // sell_price
            Generators::choose(0, 5000)        // overpayment
        )->then(function (int $sellPrice, int $overpayment) {
            $paidAmount = $sellPrice + $overpayment;

            $product = Product::create([
                'sku'                 => 'SKU-P17B-' . uniqid(),
                'name'                => 'Product P17B',
                'unit'                => 'pcs',
                'buy_price'           => $sellPrice,
                'sell_price'          => $sellPrice,
                'stock'               => 100,
                'low_stock_threshold' => 5,
                'category_id'         => $this->category->id,
                'is_available'        => true,
            ]);

            $order = Order::create([
                'order_number' => 'ORD-P17B-' . uniqid(),
                'order_code'   => 'P17B-' . uniqid(),
                'created_by'   => $this->kasir->id,
                'order_type'   => 'take_away',
                'status'       => 'pending',
            ]);

            OrderItem::create([
                'order_id'   => $order->id,
                'product_id' => $product->id,
                'quantity'   => 1,
                'unit_price' => $sellPrice,
                'subtotal'   => $sellPrice,
            ]);

            $order->load('orderItems');

            $exceptionThrown = false;
            try {
                $transaction = $this->checkoutService->processCheckout($order, [
                    'payment_method' => 'cash',
                    'paid_amount'    => $paidAmount,
                ]);
                $this->assertNotNull($transaction->id);
            } catch (InvalidPaymentException $e) {
                $exceptionThrown = true;
            }

            $this->assertFalse($exceptionThrown,
                "No exception when paid ({$paidAmount}) >= total ({$sellPrice})");

            // Cleanup
            $order->refresh();
            if ($order->transaction) {
                \App\Models\IncomeEntry::where('transaction_id', $order->transaction->id)->delete();
                $order->transaction->delete();
            }
            $order->orderItems()->delete();
            $order->delete();
            $product->delete();
        });
    }
}
