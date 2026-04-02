<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(): JsonResponse
    {
        $today = now()->toDateString();
        $sevenDaysAgo = now()->subDays(6)->toDateString();

        // Total sales & transactions today (status = 'success')
        $todayStats = DB::selectOne(
            "SELECT
                COALESCE(SUM(total_amount), 0) AS total_sales_today,
                COUNT(*) AS total_transactions_today
             FROM transactions
             WHERE status = 'success'
               AND DATE(created_at) = ?",
            [$today]
        );

        // Critical stock items: stock <= low_stock_threshold
        $criticalStockItems = DB::select(
            "SELECT id, name, sku, stock, low_stock_threshold
             FROM products
             WHERE stock <= low_stock_threshold
             ORDER BY stock ASC"
        );

        // Sales chart for last 7 days (including today)
        $salesChart = DB::select(
            "SELECT
                DATE(created_at) AS date,
                COALESCE(SUM(total_amount), 0) AS total
             FROM transactions
             WHERE status = 'success'
               AND DATE(created_at) BETWEEN ? AND ?
             GROUP BY DATE(created_at)
             ORDER BY date ASC",
            [$sevenDaysAgo, $today]
        );

        // Fill in missing days with 0
        $salesChartMap = [];
        foreach ($salesChart as $row) {
            $salesChartMap[$row->date] = (float) $row->total;
        }

        $salesChart7Days = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i)->toDateString();
            $salesChart7Days[] = [
                'date'  => $date,
                'total' => $salesChartMap[$date] ?? 0.0,
            ];
        }

        // Occupied tables count
        $occupiedTablesCount = DB::selectOne(
            "SELECT COUNT(*) AS cnt FROM tables WHERE status = 'occupied'"
        );

        return response()->json([
            'total_sales_today'       => (float) $todayStats->total_sales_today,
            'total_transactions_today' => (int) $todayStats->total_transactions_today,
            'critical_stock_items'    => array_map(fn($item) => [
                'id'                  => $item->id,
                'name'                => $item->name,
                'sku'                 => $item->sku,
                'stock'               => (float) $item->stock,
                'low_stock_threshold' => (float) $item->low_stock_threshold,
            ], $criticalStockItems),
            'sales_chart_7days'       => $salesChart7Days,
            'occupied_tables_count'   => (int) $occupiedTablesCount->cnt,
        ]);
    }
}
