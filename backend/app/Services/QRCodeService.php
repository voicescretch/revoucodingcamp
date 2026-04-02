<?php

namespace App\Services;

use App\Models\Table;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class QRCodeService
{
    /**
     * Generate the QR code URL payload for a table (idempotent).
     * Same table always produces the same URL.
     */
    public function generateTableQR(Table $table): string
    {
        return config('app.url') . '/order?table=' . $table->table_number;
    }

    /**
     * Generate the QR code SVG image for a table on-demand.
     */
    public function generateQRImage(Table $table): string
    {
        $url = $this->generateTableQR($table);

        return QrCode::format('svg')->generate($url);
    }

    /**
     * Parse the table_number from a QR payload URL and return the Table.
     */
    public function resolveTableFromQR(string $qrPayload): Table
    {
        $parsed = parse_url($qrPayload);
        parse_str($parsed['query'] ?? '', $params);

        $tableNumber = $params['table'] ?? null;

        return Table::where('table_number', $tableNumber)->firstOrFail();
    }
}
