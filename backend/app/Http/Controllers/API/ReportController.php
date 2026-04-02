<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReportRequest;
use App\Services\ReportService;
use Carbon\Carbon;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportController extends Controller
{
    public function __construct(private ReportService $reportService) {}

    /**
     * GET /api/v1/reports/stock
     * Query params: format (pdf|excel), start_date, end_date (optional)
     */
    public function stock(ReportRequest $request): Response|BinaryFileResponse
    {
        return $this->reportService->generateStockReport(
            $request->input('format'),
            $request->input('start_date'),
            $request->input('end_date'),
        );
    }

    /**
     * GET /api/v1/reports/stock-movement
     * Query params: format (pdf|excel), start_date, end_date
     */
    public function stockMovement(ReportRequest $request): Response|BinaryFileResponse
    {
        $startDate = $request->input('start_date', Carbon::today()->toDateString());
        $endDate   = $request->input('end_date', Carbon::today()->toDateString());

        return $this->reportService->generateStockMovementReport(
            $request->input('format'),
            $startDate,
            $endDate,
        );
    }

    /**
     * GET /api/v1/reports/profit-loss
     * Query params: format (pdf|excel), start_date, end_date
     */
    public function profitLoss(ReportRequest $request): Response|BinaryFileResponse
    {
        $startDate = $request->input('start_date', Carbon::today()->toDateString());
        $endDate   = $request->input('end_date', Carbon::today()->toDateString());

        return $this->reportService->generateProfitLossReport(
            $request->input('format'),
            $startDate,
            $endDate,
        );
    }
}
