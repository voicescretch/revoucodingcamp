<?php

namespace Tests\Feature;

use App\Models\ExpenseEntry;
use App\Models\IncomeEntry;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Validates: Requirements 7.3, 7.5
 */
class FinanceTest extends TestCase
{
    use DatabaseTransactions;

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function createUser(string $role = 'finance'): User
    {
        return User::factory()->create([
            'role'      => $role,
            'password'  => bcrypt('password123'),
            'is_active' => true,
        ]);
    }

    // -------------------------------------------------------------------------
    // Requirement 7.3 — tambah expense → total_expense harian bertambah
    // -------------------------------------------------------------------------

    /**
     * Test: tambah expense → total_expense harian bertambah.
     *
     * Validates: Requirement 7.3
     */
    public function test_add_expense_increases_daily_total_expense(): void
    {
        $finance = $this->createUser('finance');
        $today   = Carbon::today()->toDateString();

        // Ambil summary sebelum tambah expense
        $before = $this->actingAs($finance, 'sanctum')
            ->getJson('/api/v1/finance/summary?period=daily')
            ->assertStatus(200)
            ->json('data.total_expense');

        // Tambah expense baru
        $amount = 75000;
        $this->actingAs($finance, 'sanctum')
            ->postJson('/api/v1/expenses', [
                'date'        => $today,
                'amount'      => $amount,
                'category'    => 'Operasional',
                'description' => 'Beli sabun cuci',
            ])
            ->assertStatus(201);

        // Ambil summary setelah tambah expense
        $after = $this->actingAs($finance, 'sanctum')
            ->getJson('/api/v1/finance/summary?period=daily')
            ->assertStatus(200)
            ->json('data.total_expense');

        $this->assertEquals((float) $before + $amount, (float) $after);
    }

    /**
     * Test: tambah beberapa expense → total_expense harian bertambah sesuai jumlah.
     *
     * Validates: Requirement 7.3
     */
    public function test_add_multiple_expenses_accumulates_daily_total_expense(): void
    {
        $finance = $this->createUser('finance');
        $today   = Carbon::today()->toDateString();

        $amounts = [50000, 120000, 30000];

        foreach ($amounts as $amount) {
            $this->actingAs($finance, 'sanctum')
                ->postJson('/api/v1/expenses', [
                    'date'        => $today,
                    'amount'      => $amount,
                    'category'    => 'Operasional',
                    'description' => 'Test expense',
                ])
                ->assertStatus(201);
        }

        $summary = $this->actingAs($finance, 'sanctum')
            ->getJson('/api/v1/finance/summary?period=daily')
            ->assertStatus(200)
            ->json('data');

        // total_expense harus mencakup semua expense yang baru ditambahkan
        $this->assertGreaterThanOrEqual(array_sum($amounts), (float) $summary['total_expense']);
    }

    // -------------------------------------------------------------------------
    // Requirement 7.5 — finance summary menghitung net_profit dengan benar
    // -------------------------------------------------------------------------

    /**
     * Test: finance summary menghitung net_profit = total_income - total_expense.
     *
     * Validates: Requirement 7.5
     */
    public function test_finance_summary_calculates_net_profit_correctly(): void
    {
        $finance = $this->createUser('finance');
        $today   = Carbon::today()->toDateString();

        // Buat income entry langsung ke DB
        IncomeEntry::create([
            'date'       => $today,
            'amount'     => 500000,
            'category'   => 'Penjualan',
            'description'=> 'Pendapatan test',
            'source'     => 'manual',
            'status'     => 'validated',
            'created_by' => $finance->id,
        ]);

        // Buat expense entry langsung ke DB
        ExpenseEntry::create([
            'date'        => $today,
            'amount'      => 150000,
            'category'    => 'Operasional',
            'description' => 'Pengeluaran test',
            'created_by'  => $finance->id,
        ]);

        $summary = $this->actingAs($finance, 'sanctum')
            ->getJson('/api/v1/finance/summary?period=daily')
            ->assertStatus(200)
            ->json('data');

        $expectedNetProfit = (float) $summary['total_income'] - (float) $summary['total_expense'];

        $this->assertEquals($expectedNetProfit, (float) $summary['net_profit']);
    }

    /**
     * Test: finance summary menandai is_loss = true ketika pengeluaran > pemasukan.
     *
     * Validates: Requirement 7.5, 7.6
     */
    public function test_finance_summary_marks_is_loss_when_expense_exceeds_income(): void
    {
        $finance = $this->createUser('finance');
        $today   = Carbon::today()->toDateString();

        // Expense lebih besar dari income
        IncomeEntry::create([
            'date'        => $today,
            'amount'      => 100000,
            'category'    => 'Penjualan',
            'description' => 'Income kecil',
            'source'      => 'manual',
            'status'      => 'validated',
            'created_by'  => $finance->id,
        ]);

        ExpenseEntry::create([
            'date'        => $today,
            'amount'      => 300000,
            'category'    => 'Operasional',
            'description' => 'Expense besar',
            'created_by'  => $finance->id,
        ]);

        $summary = $this->actingAs($finance, 'sanctum')
            ->getJson('/api/v1/finance/summary?period=daily')
            ->assertStatus(200)
            ->json('data');

        $this->assertTrue((bool) $summary['is_loss']);
        $this->assertLessThan(0, (float) $summary['net_profit']);
    }

    /**
     * Test: finance summary menandai is_loss = false ketika pemasukan >= pengeluaran.
     *
     * Validates: Requirement 7.5
     */
    public function test_finance_summary_is_not_loss_when_income_exceeds_expense(): void
    {
        $finance = $this->createUser('finance');
        $today   = Carbon::today()->toDateString();

        IncomeEntry::create([
            'date'        => $today,
            'amount'      => 500000,
            'category'    => 'Penjualan',
            'description' => 'Income besar',
            'source'      => 'manual',
            'status'      => 'validated',
            'created_by'  => $finance->id,
        ]);

        ExpenseEntry::create([
            'date'        => $today,
            'amount'      => 200000,
            'category'    => 'Operasional',
            'description' => 'Expense kecil',
            'created_by'  => $finance->id,
        ]);

        $summary = $this->actingAs($finance, 'sanctum')
            ->getJson('/api/v1/finance/summary?period=daily')
            ->assertStatus(200)
            ->json('data');

        $this->assertFalse((bool) $summary['is_loss']);
        $this->assertGreaterThanOrEqual(0, (float) $summary['net_profit']);
    }

    /**
     * Test: role selain finance/head_manager tidak bisa akses POST /api/v1/expenses → 403.
     *
     * Validates: Requirement 7.3
     */
    public function test_kasir_cannot_create_expense(): void
    {
        $kasir = $this->createUser('kasir');

        $this->actingAs($kasir, 'sanctum')
            ->postJson('/api/v1/expenses', [
                'date'     => Carbon::today()->toDateString(),
                'amount'   => 50000,
                'category' => 'Operasional',
            ])
            ->assertStatus(403);
    }
}
