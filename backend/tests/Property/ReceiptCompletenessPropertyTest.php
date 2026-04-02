<?php

namespace Tests\Property;

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
 * Property 19: Struk Mengandung Semua Field yang Diperlukan
 *
 * For any successful transaction, the receipt data SHALL contain all required fields.
 *
 * Validates: Requirements 6.6
 */
class ReceiptCompletenessPropertyTest extends TestCase
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
        $this->category = Category::create(['name' => 'Cat-P19-' . uniqid(), 'type' => 'product']);
        $this->kasir = User::factory()->create(['role' => 'kasir', 'is_active' => true]);
        $this->actingAs($this->kasir, 'sanctum');
    }

    /**
     * Receipt from API contains all required fields.
     *
     * **Validates: Requirements 6.6**
     */
    public function testReceiptContainsAllRequiredFields(): void
    {
        $this->limitTo(5)->forAll(
            Generators::choose(5000, 20000),  // sell_price
            Generators::choose(1, 3)           // qty
        )->then(function (int $sellPrice, int $qty) {
            $product = Product::create([
                'sku'                 => 'SKU-P19-' . uniqid(),
                'name'                => 'Product P19 ' . uniqid(),
                'unit'                => 'pcs',
                'buy_price'           => $sellPrice,
                'sell_price'          => $sellPrice,
                'stock'               => 100,
                'low_stock_threshold' => 5,
                'category_id'         => $this->category->id,
                'is_available'        => true,
            ]);

            $totalAmount = $sellPrice * $qty;
            $paidAmount  = $totalAmount + 1000;

            $order = Order::create([
                'order_number' => 'ORD-P19-' . uniqid(),
                'order_code'   => 'P19-' . uniqid(),
                'created_by'   => $this->kasir->id,
                'order_type'   => 'take_away',
                'status'       => 'pending',
            ]);

            OrderItem::create([
                'order_id'   => $order->id,
                'product_id' => $product->id,
                'quantity'   => $qty,
                'unit_price' => $sellPrice,
                'subtotal'   => $totalAmount,
            ]);

            $order->load('orderItems');

            $transaction = $this->checkoutService->processCheckout($order, [
                'payment_method' => 'cash',
                'paid_amount'    => $paidAmount,
            ]);

            // Fetch receipt via API
            $response = $this->withToken($this->kasir->createToken('t')->plainTextToken)
                ->getJson("/api/v1/transactions/{$transaction->id}/receipt");

            $response->assertStatus(200);
            $data = $response->json('data');

            // Assert all required fields present
            $this->assertArrayHasKey('transaction_number', $data);
            $this->assertArrayHasKey('datetime', $data);
            $this->assertArrayHasKey('items', $data);
            $this->assertArrayHasKey('total_amount', $data);
            $this->assertArrayHasKey('paid_amount', $data);
            $this->assertArrayHasKey('change_amount', $data);
            $this->assertArrayHasKey('payment_method', $data);

            // Assert items have required sub-fields
            $this->assertNotEmpty($data['items']);
            $firstItem = $data['items'][0];
            $this->assertArrayHasKey('name', $firstItem);
            $this->assertArrayHasKey('qty', $firstItem);
            $this->assertArrayHasKey('unit_price', $firstItem);
            $this->assertArrayHasKey('subtotal', $firstItem);

            // Assert values are correct
            $this->assertEquals($totalAmount, $data['total_amount']);
            $this->assertEquals($paidAmount, $data['paid_amount']);
            $this->assertEquals($paidAmount - $totalAmount, $data['change_amount']);

            // Cleanup
            $this->app->make('auth')->forgetGuards();
            $order->fresh()->transaction?->incomeEntry?->delete();
            $order->fresh()->transaction?->delete();
            $order->orderItems()->delete();
            $order->delete();
            $product->delete();
        });
    }
}
