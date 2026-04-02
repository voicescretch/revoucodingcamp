<?php

namespace App\Services;

use App\Exports\ProfitLossExport;
use App\Exports\StockExport;
use App\Exports\StockMovementExport;
use App\Models\ExpenseEntry;
use App\Models\IncomeEntry;
use App\Models\Product;
use App\Models\StockMovement;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportService
{
    /**
     * Generate laporan stok semua produk.
     * Memuat: daftar produk, stok saat ini, nilai stok (buy_price × stock), status low_stock.
     */
    public function generateStockReport(string $format, ?string $startDate, ?string $endDate): Response|BinaryFileResponse
    {
        $products = Product::with('category')->get()->map(function (Product $product) {
            return [
                'id'          => $product->id,
                'sku'         => $product->sku,
                'name'        => $product->name,
                'category'    => $product->category?->name ?? '-',
                'unit'        => $product->unit,
                'stock'       => (float) $product->stock,
                'buy_price'   => (float) $product->buy_price,
                'stock_value' => round((float) $product->buy_price * (float) $product->stock, 2),
                'low_stock_threshold' => (float) $product->low_stock_threshold,
                'is_low_stock' => (float) $product->stock <= (float) $product->low_stock_threshold,
            ];
        });

        $data = [
            'title'      => 'Laporan Stok',
            'generated_at' => now()->format('d/m/Y H:i'),
            'products'   => $products,
            'total_stock_value' => $products->sum('stock_value'),
        ];

        if ($format === 'pdf') {
            $pdf = Pdf::loadView('reports.stock', $data);
            return response($pdf->output(), 200, [
                'Content-Type'        => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="laporan-stok-' . now()->format('Ymd') . '.pdf"',
            ]);
        }

        return Excel::download(new StockExport($products->toArray()), 'laporan-stok-' . now()->format('Ymd') . '.xlsx');
    }

    /**
     * Generate laporan riwayat pergerakan stok dalam rentang tanggal.
     */
    public function generateStockMovementReport(string $format, string $startDate, string $endDate): Response|BinaryFileResponse
    {
        $movements = StockMovement::with(['product', 'creator'])
            ->whereBetween('created_at', [
                Carbon::parse($startDate)->startOfDay(),
                Carbon::parse($endDate)->endOfDay(),
            ])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function (StockMovement $movement) {
                return [
                    'id'             => $movement->id,
                    'date'           => $movement->created_at->format('d/m/Y H:i'),
                    'product_sku'    => $movement->product?->sku ?? '-',
                    'product_name'   => $movement->product?->name ?? '-',
                    'type'           => $movement->type,
                    'quantity'       => (float) $movement->quantity,
                    'stock_before'   => (float) $movement->stock_before,
                    'stock_after'    => (float) $movement->stock_after,
                    'reference_type' => $movement->reference_type ?? '-',
                    'reference_id'   => $movement->reference_id,
                    'notes'          => $movement->notes ?? '-',
                    'created_by'     => $movement->creator?->name ?? '-',
                ];
            });

        $data = [
            'title'      => 'Laporan Arus Barang',
            'start_date' => Carbon::parse($startDate)->format('d/m/Y'),
            'end_date'   => Carbon::parse($endDate)->format('d/m/Y'),
            'generated_at' => now()->format('d/m/Y H:i'),
            'movements'  => $movements,
            'total_in'   => $movements->where('type', 'in')->sum('quantity'),
            'total_out'  => $movements->where('type', 'out')->sum('quantity'),
        ];

        if ($format === 'pdf') {
            $pdf = Pdf::loadView('reports.stock-movement', $data);
            return response($pdf->output(), 200, [
                'Content-Type'        => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="laporan-arus-barang-' . now()->format('Ymd') . '.pdf"',
            ]);
        }

        return Excel::download(
            new StockMovementExport($movements->toArray()),
            'laporan-arus-barang-' . now()->format('Ymd') . '.xlsx'
        );
    }

    /**
     * Generate laporan laba-rugi: total pemasukan, pengeluaran, dan laba bersih.
     */
    public function generateProfitLossReport(string $format, string $startDate, string $endDate): Response|BinaryFileResponse
    {
        $incomeEntries = IncomeEntry::whereBetween('date', [$startDate, $endDate])
            ->get()
            ->map(function (IncomeEntry $entry) {
                return [
                    'date'        => $entry->date->format('d/m/Y'),
                    'category'    => $entry->category ?? '-',
                    'description' => $entry->description ?? '-',
                    'source'      => $entry->source,
                    'amount'      => (float) $entry->amount,
                ];
            });

        $expenseEntries = ExpenseEntry::whereBetween('date', [$startDate, $endDate])
            ->get()
            ->map(function (ExpenseEntry $entry) {
                return [
                    'date'        => $entry->date->format('d/m/Y'),
                    'category'    => $entry->category ?? '-',
                    'description' => $entry->description ?? '-',
                    'amount'      => (float) $entry->amount,
                ];
            });

        $totalIncome  = $incomeEntries->sum('amount');
        $totalExpense = $expenseEntries->sum('amount');
        $netProfit    = $totalIncome - $totalExpense;

        $data = [
            'title'          => 'Laporan Laba-Rugi',
            'start_date'     => Carbon::parse($startDate)->format('d/m/Y'),
            'end_date'       => Carbon::parse($endDate)->format('d/m/Y'),
            'generated_at'   => now()->format('d/m/Y H:i'),
            'income_entries' => $incomeEntries,
            'expense_entries' => $expenseEntries,
            'total_income'   => $totalIncome,
            'total_expense'  => $totalExpense,
            'net_profit'     => $netProfit,
            'is_loss'        => $netProfit < 0,
        ];

        if ($format === 'pdf') {
            $pdf = Pdf::loadView('reports.profit-loss', $data);
            return response($pdf->output(), 200, [
                'Content-Type'        => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="laporan-laba-rugi-' . now()->format('Ymd') . '.pdf"',
            ]);
        }

        return Excel::download(
            new ProfitLossExport($incomeEntries->toArray(), $expenseEntries->toArray(), $totalIncome, $totalExpense, $netProfit),
            'laporan-laba-rugi-' . now()->format('Ymd') . '.xlsx'
        );
    }
}
