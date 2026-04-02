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
 * Property 14: Self-Order Menghasilkan Order Code Unik
 *
 * For any collection of self-orders created, every order SHALL have a unique
 * order_code — no two orders share the same order_code.
 *
 * Validates: Requirements 5.2
 */
class OrderCodeUniquenessPropertyTest extends TestCase
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
     * Creating multiple self-orders results in unique order_codes.
     *
     * **Validates: Requirements 5.2**
     */
    public function testSelfOrdersHaveUniqueOrderCodes(): void
    {
        $this->limitTo(5)->forAll(
            Generators::choose(2, 5)
        )->then(function (int $n) {
            $createdOrders = [];
            $createdTables = [];
            $createdProducts = [];

            // Create a shared product for all orders
            $product = Product::create([
                'sku'                 => 'SKU-ORD-' . uniqid(),
                'name'                => 'Test Product ' . uniqid(),
                'unit'                => 'pcs',
                'buy_price'           => 5000,
                'sell_price'          => 8000,
                'stock'               => 100,
                'low_stock_threshold' => 5,
                'category_id'         => $this->category->id,
                'is_available'        => true,
            ]);
            $createdProducts[] = $product;

            // Create N tables and N self-orders
            for ($i = 0; $i < $n; $i++) {
                $table = Table::create([
                    'table_number' => 'T-PROP14-' . uniqid(),
                    'name'         => 'Table Prop14 ' . $i,
                    'capacity'     => 4,
                    'status'       => 'available',
                ]);
                $createdTables[] = $table;

                $order = $this->orderService->createSelfOrder(
                    [['product_id' => $product->id, 'quantity' => 1]],
                    $table->table_number
                );
                $createdOrders[] = $order;
            }

            // Collect all order_codes
            $orderCodes = array_map(fn ($o) => $o->order_code, $createdOrders);

            // Verify all order_codes are unique (no duplicates)
            $uniqueCodes = array_unique($orderCodes);
            $this->assertCount(
                count($orderCodes),
                $uniqueCodes,
                "All {$n} self-orders must have unique order_codes. Got: " . implode(', ', $orderCodes)
            );

            // Cleanup
            foreach ($createdOrders as $order) {
                $order->orderItems()->delete();
                $order->delete();
            }
            foreach ($createdTables as $table) {
                $table->delete();
            }
            foreach ($createdProducts as $p) {
                $p->delete();
            }
        });
    }
}
