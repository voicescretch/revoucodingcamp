<?php

namespace Tests\Property;

use App\Models\Table;
use App\Services\QRCodeService;
use App\Services\TableService;
use Eris\Generators;
use Eris\TestTrait;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Property 13: QR Code Generation Bersifat Idempotent
 *
 * For any registered table, calling the QR code generation function multiple times
 * SHALL always produce a QR code pointing to the same table identity (equivalent URL/payload).
 *
 * Validates: Requirements 4.10
 */
class QrCodeIdempotencyPropertyTest extends TestCase
{
    use DatabaseTransactions;
    use TestTrait;

    private QRCodeService $qrCodeService;
    private TableService $tableService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->qrCodeService = app(QRCodeService::class);
        $this->tableService  = app(TableService::class);
    }

    /**
     * Calling QRCodeService::generateTableQR multiple times on the same table always returns the same URL.
     *
     * **Validates: Requirements 4.10**
     */
    public function testQrCodeGenerationIsIdempotent(): void
    {
        $this->limitTo(5)->forAll(
            Generators::string()
        )->then(function (string $rawTableNumber) {
            // Sanitize to alphanumeric, max 20 chars
            $tableNumber = substr(preg_replace('/[^a-zA-Z0-9]/', '', $rawTableNumber), 0, 20);

            if (empty($tableNumber)) {
                $tableNumber = 'T' . uniqid();
            }

            $table = $this->tableService->create([
                'table_number' => $tableNumber,
                'name'         => 'Table ' . $tableNumber,
                'capacity'     => 4,
            ]);

            // Call generateTableQR 3 times and verify all results are identical
            $result1 = $this->qrCodeService->generateTableQR($table);
            $result2 = $this->qrCodeService->generateTableQR($table);
            $result3 = $this->qrCodeService->generateTableQR($table);

            $this->assertSame(
                $result1,
                $result2,
                "QR code generation must be idempotent: call 1 and call 2 differ for table_number={$tableNumber}"
            );

            $this->assertSame(
                $result2,
                $result3,
                "QR code generation must be idempotent: call 2 and call 3 differ for table_number={$tableNumber}"
            );

            // Cleanup
            $table->delete();
        });
    }
}
