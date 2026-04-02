<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\ExpenseEntry;
use App\Models\IncomeEntry;
use App\Services\FinanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class FinanceController extends Controller
{
    public function __construct(private FinanceService $financeService) {}

    /**
     * GET /api/v1/expenses
     * List expenses with optional date filters.
     */
    public function indexExpenses(Request $request): JsonResponse
    {
        $query = ExpenseEntry::with('creator')->orderByDesc('date');

        if ($request->filled('start_date')) {
            $query->whereDate('date', '>=', $request->start_date);
        }

        if ($request->filled('end_date')) {
            $query->whereDate('date', '<=', $request->end_date);
        }

        return response()->json(['data' => $query->get()]);
    }

    /**
     * POST /api/v1/expenses
     * Create a new expense entry (Finance role).
     */
    public function storeExpense(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'date'        => 'required|date',
            'amount'      => 'required|numeric|min:0',
            'category'    => 'required|string|max:255',
            'description' => 'nullable|string',
            'receipt_path'=> 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed.', 'errors' => $validator->errors()], 422);
        }

        $expense = ExpenseEntry::create([
            'date'         => $request->date,
            'amount'       => $request->amount,
            'category'     => $request->category,
            'description'  => $request->description,
            'receipt_path' => $request->receipt_path,
            'created_by'   => Auth::id(),
        ]);

        return response()->json(['data' => $expense], 201);
    }

    /**
     * GET /api/v1/income
     * List income entries with optional date filters.
     */
    public function indexIncome(Request $request): JsonResponse
    {
        $query = IncomeEntry::with('creator', 'transaction')->orderByDesc('date');

        if ($request->filled('start_date')) {
            $query->whereDate('date', '>=', $request->start_date);
        }

        if ($request->filled('end_date')) {
            $query->whereDate('date', '<=', $request->end_date);
        }

        return response()->json(['data' => $query->get()]);
    }

    /**
     * PUT /api/v1/income/{id}/validate
     * Update income entry status to 'validated'.
     */
    public function validateIncome(int $id): JsonResponse
    {
        $income = IncomeEntry::find($id);

        if (!$income) {
            return response()->json(['message' => 'Income entry not found.'], 404);
        }

        $income->update(['status' => 'validated']);

        return response()->json(['data' => $income]);
    }

    /**
     * GET /api/v1/finance/summary
     * Financial summary — daily/weekly/monthly.
     */
    public function summary(Request $request): JsonResponse
    {
        $period = $request->get('period', 'daily');

        if (!in_array($period, ['daily', 'weekly', 'monthly'])) {
            return response()->json(['message' => "Invalid period. Use 'daily', 'weekly', or 'monthly'."], 422);
        }

        $summary = $this->financeService->getSummary(
            $period,
            $request->get('start_date'),
            $request->get('end_date')
        );

        return response()->json(['data' => $summary]);
    }
}
