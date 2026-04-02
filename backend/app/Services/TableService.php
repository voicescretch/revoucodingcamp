<?php

namespace App\Services;

use App\Models\Table;

class TableService
{
    public function __construct(private QRCodeService $qrCodeService) {}

    /**
     * Create a new table, auto-generate and store the QR code URL.
     */
    public function create(array $data): Table
    {
        $table = Table::create($data);

        $qrUrl = $this->qrCodeService->generateTableQR($table);
        $table->update(['qr_code' => $qrUrl]);

        return $table->fresh();
    }

    /**
     * Update the status of a table.
     */
    public function updateStatus(Table $table, string $status): Table
    {
        $table->update(['status' => $status]);

        return $table->fresh();
    }
}
