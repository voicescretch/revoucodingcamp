<?php

namespace Tests\Property;

use App\Models\ExpenseEntry;
use App\Models\IncomeEntry;
use App\Models\User;
use App\Services\FinanceService;
use Eris\Generators;
use Eris\TestTrait;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Property 21: Kalkulasi Summary Keuangan
 *
 * For any set of income and expense entries in a date range:
 * net_profit = total_income - total_expense always holds.
 * If net_profit < 0, is_loss must be true.
 *
 * Validates: Requirements 7.5
 */
class FinanceSummaryPropertyTest extends TestCase
{
    use DatabaseTransactions;
    use TestTrait;

    private FinanceService $financeService;
    private User $user;
    private string $testDate;

    protected function setUp(): void
    {
        parent::setUp();

        $this->financeService = app(FinanceService::class);

        $this->user = User::factory()->create([
            'role'      => 'finance',
            'is_active' => true,
        ]);
        $this->actingAs($this->user, 'sanctum');

        // Use a fixed date far in the past to avoid collisions with other tests
        $this->testDate = '2000-01-01';
    }

    /**
     * net_profit = total_income - total_expense for any non-negative income/expense values.
     *
     * **Validates: Requirements 7.5**
     */
    public function testNetProfitEqualsIncomMinusExpense(): void
    {
        $this->limitTo(10)->forAll(
            Generators::choose(0, 10),  // number of income entries
            Generators::choose(0, 10),  // number of expense entries
            Generators::choose(1, 100000), // income amount per entry (cents)
            Generators::choose(1, 100000)  // expense amount per entry (cents)
        )->then(function (int $incomeCount, int $expenseCount, int $incomeAmount, int $expenseAmount) {
            // Use a unique date per iteration to isolate data
            $date = '1999-' . str_pad(rand(1, 12), 2, '0', STR_PAD_LEFT) . '-' . str_pad(rand(1, 28), 2, '0', STR_PAD_LEFT);

            // Seed income entries
            for ($i = 0; $i < $incomeCount; $i++) {
                IncomeEntry::create([
                    'date'       => $date,
                    'amount'     => $incomeAmount,
                    'category'   => 'Penjualan',
                    'source'     => 'manual',
                    'status'     => 'pending',
                    'created_by' => $this->user->id,
                ]);
            }

            // Seed expense entries
            for ($i = 0; $i < $expenseCount; $i++) {
                ExpenseEntry::create([
                    'date'       => $date,
                    'amount'     => $expenseAmount,
                    'category'   => 'Operasional',
                    'created_by' => $this->user->id,
                ]);
            }

            $summary = $this->financeService->getSummary('daily', $date, $date);

            $expectedIncome  = $incomeCount * $incomeAmount;
            $expectedExpense = $expenseCount * $expenseAmount;
            $expectedProfit  = $expectedIncome - $expectedExpense;

            // Property: net_profit = total_income - total_expense
            $this->assertEqualsWithDelta(
                $expectedProfit,
                $summary['net_profit'],
                0.01,
                "net_profit must equal total_income - total_expense"
            );

            // Property: is_loss is true iff net_profit < 0
            $this->assertEquals(
                $expectedProfit < 0,
                $summary['is_loss'],
                "is_loss must be true iff net_profit < 0"
            );

            // Property: total_income and total_expense are non-negative
            $this->assertGreaterThanOrEqual(0, $summary['total_income'], "total_income must be >= 0");
            $this->assertGreaterThanOrEqual(0, $summary['total_expense'], "total_expense must be >= 0");

            // Cleanup for next iteration
            IncomeEntry::whereDate('date', $date)->delete();
            ExpenseEntry::whereDate('date', $date)->delete();
        });
    }

    /**
     * When total_expense > total_income, is_loss must be true.
     *
     * **Validates: Requirements 7.6**
     */
    public function testIsLossWhenExpenseExceedsIncome(): void
    {
        $this->limitTo(10)->forAll(
            Generators::choose(1, 50000),  // income amount
            Generators::choose(1, 50000)   // extra expense on top of income
        )->then(function (int $income, int $extra) {
            $date = '1998-' . str_pad(rand(1, 12), 2, '0', STR_PAD_LEFT) . '-' . str_pad(rand(1, 28), 2, '0', STR_PAD_LEFT);

            IncomeEntry::create([
                'date'       => $date,
                'amount'     => $income,
                'category'   => 'Penjualan',
                'source'     => 'manual',
                'status'     => 'pending',
                'created_by' => $this->user->id,
            ]);

            // Expense is always income + extra, so net_profit is always negative
            ExpenseEntry::create([
                'date'       => $date,
                'amount'     => $income + $extra,
                'category'   => 'Operasional',
                'created_by' => $this->user->id,
            ]);

            $summary = $this->financeService->getSummary('daily', $date, $date);

            $this->assertTrue($summary['is_loss'], "is_loss must be true when expense > income");
            $this->assertLessThan(0, $summary['net_profit'], "net_profit must be negative when expense > income");

            // Cleanup
            IncomeEntry::whereDate('date', $date)->delete();
            ExpenseEntry::whereDate('date', $date)->delete();
        });
    }

    /**
     * When total_income >= total_expense, is_loss must be false.
     *
     * **Validates: Requirements 7.5**
     */
    public function testNotLossWhenIncomeCoversExpense(): void
    {
        $this->limitTo(10)->forAll(
            Generators::choose(1, 50000),  // expense amount
            Generators::choose(0, 50000)   // extra income on top of expense
        )->then(function (int $expense, int $extra) {
            $date = '1997-' . str_pad(rand(1, 12), 2, '0', STR_PAD_LEFT) . '-' . str_pad(rand(1, 28), 2, '0', STR_PAD_LEFT);

            // Income is always expense + extra, so net_profit is always >= 0
            IncomeEntry::create([
                'date'       => $date,
                'amount'     => $expense + $extra,
                'category'   => 'Penjualan',
                'source'     => 'manual',
                'status'     => 'pending',
                'created_by' => $this->user->id,
            ]);

            ExpenseEntry::create([
                'date'       => $date,
                'amount'     => $expense,
                'category'   => 'Operasional',
                'created_by' => $this->user->id,
            ]);

            $summary = $this->financeService->getSummary('daily', $date, $date);

            $this->assertFalse($summary['is_loss'], "is_loss must be false when income >= expense");
            $this->assertGreaterThanOrEqual(0, $summary['net_profit'], "net_profit must be >= 0 when income >= expense");

            // Cleanup
            IncomeEntry::whereDate('date', $date)->delete();
            ExpenseEntry::whereDate('date', $date)->delete();
        });
    }
}
