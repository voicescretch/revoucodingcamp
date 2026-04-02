<?php

namespace Tests\Property;

use App\Models\Category;
use App\Models\User;
use Eris\Generators;
use Eris\TestTrait;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Property 5: SKU Bersifat Unik
 *
 * For any two different stock items, they SHALL have different SKU values.
 * Attempting to save an item with an existing SKU SHALL be rejected by the system.
 *
 * Validates: Requirements 2.2
 */
class SkuUniquenessPropertyTest extends TestCase
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
        $this->managerToken = $this->manager->createToken('manager_setup')->plainTextToken;

        $this->category = Category::create([
            'name' => 'Test Category ' . uniqid(),
            'type' => 'product',
        ]);
    }

    /**
     * For any SKU string, inserting a product with a duplicate SKU must be rejected (409).
     *
     * **Validates: Requirements 2.2**
     */
    public function testDuplicateSkuIsRejectedByApi(): void
    {
        $this->limitTo(5)->forAll(
            Generators::string()
        )->then(function (string $rawSku) {
            // Sanitize to alphanumeric, max 50 chars
            $sku = substr(preg_replace('/[^a-zA-Z0-9]/', '', $rawSku), 0, 50);

            // Ensure SKU is not empty after sanitization
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
            $firstResponse = $this->withToken($this->managerToken)
                ->postJson('/api/v1/products', $payload);

            $firstResponse->assertStatus(201);

            // Second insert with same SKU must be rejected.
            // The system may return 409 (DB unique constraint) or 422 (form validation),
            // both indicate the duplicate SKU was correctly rejected.
            $secondResponse = $this->withToken($this->managerToken)
                ->postJson('/api/v1/products', $payload);

            $this->assertContains(
                $secondResponse->status(),
                [409, 422],
                "Duplicate SKU must be rejected with 409 or 422, got {$secondResponse->status()}"
            );

            // Cleanup: delete the created product
            $productId = $firstResponse->json('data.id');
            if ($productId) {
                $this->withToken($this->managerToken)
                    ->deleteJson("/api/v1/products/{$productId}");
            }
        });
    }

    /**
     * Two products with different SKUs can both be created successfully.
     *
     * **Validates: Requirements 2.2**
     */
    public function testDistinctSkusAreAccepted(): void
    {
        $this->limitTo(5)->forAll(
            Generators::string()
        )->then(function (string $rawSku) {
            // Sanitize to alphanumeric, max 20 chars
            $base = substr(preg_replace('/[^a-zA-Z0-9]/', '', $rawSku), 0, 20);

            if (empty($base)) {
                $base = 'BASE';
            }

            $sku1 = $base . 'A' . uniqid();
            $sku2 = $base . 'B' . uniqid();

            $payload1 = [
                'sku'        => substr($sku1, 0, 50),
                'name'       => 'Product A ' . $base,
                'unit'       => 'pcs',
                'buy_price'  => 1000,
                'sell_price' => 1500,
                'category_id' => $this->category->id,
            ];

            $payload2 = [
                'sku'        => substr($sku2, 0, 50),
                'name'       => 'Product B ' . $base,
                'unit'       => 'pcs',
                'buy_price'  => 2000,
                'sell_price' => 2500,
                'category_id' => $this->category->id,
            ];

            $response1 = $this->withToken($this->managerToken)
                ->postJson('/api/v1/products', $payload1);

            $response2 = $this->withToken($this->managerToken)
                ->postJson('/api/v1/products', $payload2);

            $response1->assertStatus(201);
            $response2->assertStatus(201);

            // Cleanup
            $id1 = $response1->json('data.id');
            $id2 = $response2->json('data.id');

            if ($id1) {
                $this->withToken($this->managerToken)->deleteJson("/api/v1/products/{$id1}");
            }
            if ($id2) {
                $this->withToken($this->managerToken)->deleteJson("/api/v1/products/{$id2}");
            }
        });
    }
}
