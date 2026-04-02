<?php

namespace Tests\Property;

use App\Models\Category;
use App\Models\Product;
use App\Models\Table;
use App\Models\User;
use App\Services\OrderService;
use Eris\Generators;
use Eris\TestTrait;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Property 16: Table Status Lifecycle
 *
 * For any table linked to a dine_in or self_order:
 * (a) when the order is successfully created, table status SHALL become 'occupied'
 * (b) when the order status becomes 'completed' or 'cancelled', table status SHALL return to 'available'
 *
 * Validates: Requirements 5.6, 5.9
 */
class TableStatusLifecyclePropertyTest extends TestCase
{
    use DatabaseTransactions;
    use TestTrait;

    private OrderService $orderService;
    private Category $category;
    private User $kasirUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->orderService = app(OrderService::class);

        $this->category = Category::create([
            'name' => 'Test Category ' . uniqid(),
            'type' => 'product',
        ]);

        $this->kasirUser = User::factory()->create([
            'role'      => 'kasir',
            'is_active' => true,
        ]);

        $this->actingAs($this->kasirUser, 'sanctum');
    }

    /**
     * (a) When dine_in or self_order is created, table status becomes 'occupied'.
     *
     * **Validates: Requirements 5.6**
     */
    public function testTableBecomesOccupiedWhenOrderCreated(): void
    {
        $this->limitTo(5)->forAll(
            Generators::elements(['dine_in', 'self_order'])
        )->then(function (string $orderType) {
            $product = Product::create([
                'sku'                 => 'SKU-LC-A-' . uniqid(),
                'name'                => 'Test Product LC-A ' . uniqid(),
                'unit'                => 'pcs',
                'buy_price'           => 5000,
                'sell_price'          => 8000,
                'stock'               => 100,
                'low_stock_threshold' => 5,
                'category_id'         => $this->category->id,
                'is_available'        => true,
            ]);

            $table = Table::create([
                'table_number' => 'T-PROP16A-' . uniqid(),
                'name'         => 'Table Prop16A',
                'capacity'     => 4,
                'status'       => 'available',
            ]);

            $items = [['product_id' => $product->id, 'quantity' => 1]];

            if ($orderType === 'self_order') {
                $order = $this->orderService->createSelfOrder($items, $table->table_number);
            } else {
                $order = $this->orderService->createCashierOrder($items, 'dine_in', $table->id);
            }

            // (a) Table status must be 'occupied' after order creation
            $table->refresh();
            $this->assertEquals(
                'occupied',
                $table->status,
                "Table status must be 'occupied' after creating a {$orderType} order"
            );

            // Cleanup
            $order->orderItems()->delete();
            $order->delete();
            $table->delete();
            $product->delete();
        });
    }

    /**
     * (b) When order status becomes 'completed' or 'cancelled', table status returns to 'available'.
     *
     * **Validates: Requirements 5.9**
     */
    public function testTableBecomesAvailableWhenOrderFinalized(): void
    {
        $this->limitTo(5)->forAll(
            Generators::elements(['completed', 'cancelled'])
        )->then(function (string $finalStatus) {
            $product = Product::create([
                'sku'                 => 'SKU-LC-B-' . uniqid(),
                'name'                => 'Test Product LC-B ' . uniqid(),
                'unit'                => 'pcs',
                'buy_price'           => 5000,
                'sell_price'          => 8000,
                'stock'               => 100,
                'low_stock_threshold' => 5,
                'category_id'         => $this->category->id,
                'is_available'        => true,
            ]);

            $table = Table::create([
                'table_number' => 'T-PROP16B-' . uniqid(),
                'name'         => 'Table Prop16B',
                'capacity'     => 4,
                'status'       => 'available',
            ]);

            // Create a dine_in order (table becomes occupied)
            $order = $this->orderService->createCashierOrder(
                [['product_id' => $product->id, 'quantity' => 1]],
                'dine_in',
                $table->id
            );

            // Verify table is occupied
            $table->refresh();
            $this->assertEquals('occupied', $table->status);

            // Update order to final status
            $reason = $finalStatus === 'cancelled' ? 'Test cancellation' : null;
            $this->orderService->updateStatus($order, $finalStatus, $reason);

            // (b) Table status must return to 'available'
            $table->refresh();
            $this->assertEquals(
                'available',
                $table->status,
                "Table status must return to 'available' when order status becomes '{$finalStatus}'"
            );

            // Cleanup
            $order->orderItems()->delete();
            $order->delete();
            $table->delete();
            $product->delete();
        });
    }
}
