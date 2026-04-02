<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Illuminate\Support\Collection;

class StockExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(private array $products) {}

    public function collection(): Collection
    {
        return collect($this->products);
    }

    public function headings(): array
    {
        return [
            'ID',
            'SKU',
            'Nama Produk',
            'Kategori',
            'Satuan',
            'Stok',
            'Harga Beli',
            'Nilai Stok',
            'Batas Low Stock',
            'Status Low Stock',
        ];
    }

    public function map($row): array
    {
        return [
            $row['id'],
            $row['sku'],
            $row['name'],
            $row['category'],
            $row['unit'],
            $row['stock'],
            $row['buy_price'],
            $row['stock_value'],
            $row['low_stock_threshold'],
            $row['is_low_stock'] ? 'Ya' : 'Tidak',
        ];
    }
}
