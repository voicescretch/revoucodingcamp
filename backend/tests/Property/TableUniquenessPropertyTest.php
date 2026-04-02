<?php

namespace Tests\Property;

use App\Models\Table;
use App\Models\User;
use Eris\Generators;
use Eris\TestTrait;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Property 11: Table Number Bersifat Unik
 *
 * For any two different tables, they SHALL have different table_number values.
 * Attempting to save a table with an existing table_number SHALL be rejected by the system.
 *
 * Validates: Requirements 4.2
 */
class TableUniquenessPropertyTest extends TestCase
{
    use DatabaseTransactions;
    use TestTrait;

    private User $manager;
    private string $managerToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = User::factory()->create([
            'role'      => 'head_manager',
            'is_active' => true,
        ]);
        $this->managerToken = $this->manager->createToken('manager_setup')->plainTextToken;
    }

    /**
     * For any table_number string, inserting a table with a duplicate table_number must be rejected (409 or 422).
     *
     * **Validates: Requirements 4.2**
     */
    public function testDuplicateTableNumberIsRejectedByApi(): void
    {
        $this->limitTo(5)->forAll(
            Generators::string()
        )->then(function (string $rawTableNumber) {
            // Sanitize to alphanumeric, max 20 chars
            $tableNumber = substr(preg_replace('/[^a-zA-Z0-9]/', '', $rawTableNumber), 0, 20);

            if (empty($tableNumber)) {
                $tableNumber = 'T' . uniqid();
            }

            $payload = [
                'table_number' => $tableNumber,
                'name'         => 'Table ' . $tableNumber,
                'capacity'     => 4,
            ];

            // First insert must succeed
            $firstResponse = $this->withToken($this->managerToken)
                ->postJson('/api/v1/tables', $payload);

            $firstResponse->assertStatus(201);

            // Second insert with same table_number must be rejected.
            // The system may return 409 (DB unique constraint) or 422 (form validation),
            // both indicate the duplicate table_number was correctly rejected.
            $secondResponse = $this->withToken($this->managerToken)
                ->postJson('/api/v1/tables', $payload);

            $this->assertContains(
                $secondResponse->status(),
                [409, 422],
                "Duplicate table_number must be rejected with 409 or 422, got {$secondResponse->status()}"
            );

            // Cleanup: delete the created table
            $tableId = $firstResponse->json('data.id');
            if ($tableId) {
                Table::find($tableId)?->delete();
            }
        });
    }
}
