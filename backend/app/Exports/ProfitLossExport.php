<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;
use Illuminate\Support\Collection;

class ProfitLossExport implements WithMultipleSheets
{
    public function __construct(
        private array $incomeEntries,
        private array $expenseEntries,
        private float $totalIncome,
        private float $totalExpense,
        private float $netProfit,
    ) {}

    public function sheets(): array
    {
        return [
            new ProfitLossIncomeSheet($this->incomeEntries, $this->totalIncome),
            new ProfitLossExpenseSheet($this->expenseEntries, $this->totalExpense),
            new ProfitLossSummarySheet($this->totalIncome, $this->totalExpense, $this->netProfit),
        ];
    }
}

class ProfitLossIncomeSheet implements FromCollection, WithHeadings, WithMapping, WithTitle
{
    public function __construct(private array $entries, private float $total) {}

    public function title(): string
    {
        return 'Pemasukan';
    }

    public function collection(): Collection
    {
        return collect($this->entries);
    }

    public function headings(): array
    {
        return ['Tanggal', 'Kategori', 'Deskripsi', 'Sumber', 'Jumlah (Rp)'];
    }

    public function map($row): array
    {
        return [
            $row['date'],
            $row['category'],
            $row['description'],
            $row['source'],
            $row['amount'],
        ];
    }
}

class ProfitLossExpenseSheet implements FromCollection, WithHeadings, WithMapping, WithTitle
{
    public function __construct(private array $entries, private float $total) {}

    public function title(): string
    {
        return 'Pengeluaran';
    }

    public function collection(): Collection
    {
        return collect($this->entries);
    }

    public function headings(): array
    {
        return ['Tanggal', 'Kategori', 'Deskripsi', 'Jumlah (Rp)'];
    }

    public function map($row): array
    {
        return [
            $row['date'],
            $row['category'],
            $row['description'],
            $row['amount'],
        ];
    }
}

class ProfitLossSummarySheet implements FromCollection, WithHeadings, WithTitle
{
    public function __construct(
        private float $totalIncome,
        private float $totalExpense,
        private float $netProfit,
    ) {}

    public function title(): string
    {
        return 'Ringkasan';
    }

    public function collection(): Collection
    {
        return collect([
            ['Total Pemasukan', $this->totalIncome],
            ['Total Pengeluaran', $this->totalExpense],
            ['Laba/Rugi Bersih', $this->netProfit],
        ]);
    }

    public function headings(): array
    {
        return ['Keterangan', 'Jumlah (Rp)'];
    }
}
