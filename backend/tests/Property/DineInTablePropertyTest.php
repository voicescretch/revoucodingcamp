<?php

namespace Tests\Property;

use App\Exceptions\TableNotAvailableException;
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
 * Property 15: Dine-In Memerlukan Meja Berstatus Available
 *
 * For any order request with type dine_in, if the selected table has a status
 * other than 'available', the order SHALL be rejected with a TableNotAvailableException.
 *
 * Validates: Requirements 5.4, 5.5
 */
class DineInTablePropertyTest extends TestCase
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
     * Dine-in order is rejected when table status is not 'available'.
     *
     * **Validates: Requirements 5.4, 5.5**
     */
    public function testDineInRejectedWhenTableNotAvailable(): void
    {
        $this->limitTo(5)->forAll(
            Generators::elements(['occupied', 'reserved'])
        )->then(function (string $tableStatus) {
            $product = Product::create([
                'sku'                 => 'SKU-DINEIN-' . uniqid(),
                'name'                => 'Test Product DineIn ' . uniqid(),
                'unit'                => 'pcs',
                'buy_price'           => 5000,
                'sell_price'          => 8000,
                'stock'               => 100,
                'low_stock_threshold' => 5,
                'category_id'         => $this->category->id,
                'is_available'        => true,
            ]);

            $table = Table::create([
                'table_number' => 'T-PROP15-' . uniqid(),
                'name'         => 'Table Prop15',
                'capacity'     => 4,
                'status'       => $tableStatus,
            ]);

            $exceptionThrown = false;

            try {
                $this->orderService->createCashierOrder(
                    [['product_id' => $product->id, 'quantity' => 1]],
                    'dine_in',
                    $table->id
                );
            } catch (TableNotAvailableException $e) {
                $exceptionThrown = true;

                // Verify the exception message contains the table number and current status
                $this->assertStringContainsString(
                    $table->table_number,
                    $e->getMessage(),
                    "Exception message must contain the table number"
                );
                $this->assertStringContainsString(
                    $tableStatus,
                    $e->getMessage(),
                    "Exception message must contain the current table status"
                );
            }

            $this->assertTrue(
                $exceptionThrown,
                "TableNotAvailableException must be thrown when table status is '{$tableStatus}'"
            );

            // Cleanup
            $table->delete();
            $product->delete();
        });
    }
}
