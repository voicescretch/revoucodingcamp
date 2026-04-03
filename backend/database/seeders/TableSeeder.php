<?php

namespace Database\Seeders;

use App\Models\Table;
use App\Services\QRCodeService;
use Illuminate\Database\Seeder;

class TableSeeder extends Seeder
{
    public function run(): void
    {
        $qrService = app(QRCodeService::class);

        $tables = [
            ['table_number' => 'T01', 'name' => 'Meja 1',    'capacity' => 4],
            ['table_number' => 'T02', 'name' => 'Meja 2',    'capacity' => 4],
            ['table_number' => 'T03', 'name' => 'Meja 3',    'capacity' => 6],
            ['table_number' => 'T04', 'name' => 'Meja VIP',  'capacity' => 8],
            ['table_number' => 'T05', 'name' => 'Bar Seat 1','capacity' => 2],
        ];

        foreach ($tables as $data) {
            $table = Table::firstOrCreate(
                ['table_number' => $data['table_number']],
                array_merge($data, ['status' => 'available', 'qr_code' => ''])
            );

            // Generate QR code URL (idempotent)
            if (empty($table->qr_code)) {
                $table->update(['qr_code' => $qrService->generateTableQR($table)]);
            }
        }
    }
}
