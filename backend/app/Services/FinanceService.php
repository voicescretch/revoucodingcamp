<?php

namespace App\Services;

use App\Models\ExpenseEntry;
use App\Models\IncomeEntry;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class FinanceService
{
    /**
     * Get financial summary for a given period.
     *
     * @param  string       $period     'daily' | 'weekly' | 'monthly'
     * @param  string|null  $startDate  YYYY-MM-DD (overrides period when both provided)
     * @param  string|null  $endDate    YYYY-MM-DD
     */
    public function getSummary(string $period, ?string $startDate, ?string $endDate): array
    {
        [$start, $end] = $this->resolveDateRange($period, $startDate, $endDate);

        $totalIncome = IncomeEntry::whereBetween('date', [$start, $end])
            ->sum('amount');

        $totalExpense = ExpenseEntry::whereBetween('date', [$start, $end])
            ->sum('amount');

        $netProfit = (float) $totalIncome - (float) $totalExpense;

        return [
            'period'        => $period,
            'start_date'    => $start,
            'end_date'      => $end,
            'total_income'  => (float) $totalIncome,
            'total_expense' => (float) $totalExpense,
            'net_profit'    => $netProfit,
            'is_loss'       => $netProfit < 0,
        ];
    }

    /**
     * Resolve start/end dates from period or explicit date params.
     */
    private function resolveDateRange(string $period, ?string $startDate, ?string $endDate): array
    {
        if ($startDate && $endDate) {
            return [$startDate, $endDate];
        }

        $today = Carbon::today();

        return match ($period) {
            'weekly'  => [$today->copy()->startOfWeek()->toDateString(), $today->copy()->endOfWeek()->toDateString()],
            'monthly' => [$today->copy()->startOfMonth()->toDateString(), $today->copy()->endOfMonth()->toDateString()],
            default   => [$today->toDateString(), $today->toDateString()], // daily
        };
    }
}
