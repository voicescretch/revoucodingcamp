<?php

namespace Database\Seeders;

use App\Models\ExpenseEntry;
use App\Models\IncomeEntry;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class FinanceSeeder extends Seeder
{
    public function run(): void
    {
        $finance = User::where('role', 'finance')->first();
        $manager = User::where('role', 'head_manager')->first();
        $createdBy = $finance?->id ?? $manager?->id ?? 1;

        $today     = Carbon::today();
        $yesterday = Carbon::yesterday();
        $twoDaysAgo = Carbon::today()->subDays(2);

        // Income entries (simulasi dari transaksi POS manual)
        $incomes = [
            ['date' => $today->toDateString(),      'amount' => 450000, 'category' => 'Penjualan', 'description' => 'Penjualan sore hari', 'source' => 'manual', 'status' => 'validated'],
            ['date' => $today->toDateString(),      'amount' => 280000, 'category' => 'Penjualan', 'description' => 'Penjualan malam hari', 'source' => 'manual', 'status' => 'pending'],
            ['date' => $yesterday->toDateString(),  'amount' => 620000, 'category' => 'Penjualan', 'description' => 'Penjualan kemarin', 'source' => 'manual', 'status' => 'validated'],
            ['date' => $twoDaysAgo->toDateString(), 'amount' => 390000, 'category' => 'Penjualan', 'description' => 'Penjualan 2 hari lalu', 'source' => 'manual', 'status' => 'validated'],
        ];

        foreach ($incomes as $income) {
            IncomeEntry::create(array_merge($income, ['created_by' => $createdBy]));
        }

        // Expense entries
        $expenses = [
            ['date' => $today->toDateString(),      'amount' => 150000, 'category' => 'Bahan Baku',  'description' => 'Beli rum dan syrup'],
            ['date' => $today->toDateString(),      'amount' => 50000,  'category' => 'Operasional', 'description' => 'Sabun dan pembersih'],
            ['date' => $yesterday->toDateString(),  'amount' => 200000, 'category' => 'Bahan Baku',  'description' => 'Restock bahan minuman'],
            ['date' => $twoDaysAgo->toDateString(), 'amount' => 75000,  'category' => 'Operasional', 'description' => 'Listrik dan air'],
        ];

        foreach ($expenses as $expense) {
            ExpenseEntry::create(array_merge($expense, ['created_by' => $createdBy]));
        }
    }
}
