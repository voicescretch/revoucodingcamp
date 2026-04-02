<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Illuminate\Support\Collection;

class StockMovementExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(private array $movements) {}

    public function collection(): Collection
    {
        return collect($this->movements);
    }

    public function headings(): array
    {
        return [
            'ID',
            'Tanggal',
            'SKU Produk',
            'Nama Produk',
            'Tipe',
            'Jumlah',
            'Stok Sebelum',
            'Stok Sesudah',
            'Tipe Referensi',
            'ID Referensi',
            'Catatan',
            'Dibuat Oleh',
        ];
    }

    public function map($row): array
    {
        return [
            $row['id'],
            $row['date'],
            $row['product_sku'],
            $row['product_name'],
            $row['type'] === 'in' ? 'Masuk' : 'Keluar',
            $row['quantity'],
            $row['stock_before'],
            $row['stock_after'],
            $row['reference_type'],
            $row['reference_id'],
            $row['notes'],
            $row['created_by'],
        ];
    }
}
