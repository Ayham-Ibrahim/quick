<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Services\ReportService;
use App\Services\AdminProfitService;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function __construct(
        protected ReportService $reportService,
        protected AdminProfitService $adminProfitService
    ) {}

    public function staticsReport()
    {
        $data = $this->reportService->staticsReport();

        return $this->success($data);
    }

    /**
     * الإحصائيات المالية للإدارة
     * GET /api/reports/financial
     */
    public function financialReport()
    {
        $data = $this->adminProfitService->getFinancialStatistics();

        return $this->success($data, 'الإحصائيات المالية');
    }
}
