<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\ProfitRatiosService;
use Illuminate\Http\Request;

class ProfitRatiosController extends Controller
{
    public function __construct(
        protected ProfitRatiosService $profitRatiosService
    ) {}

    public function index()
    {
        return $this->success(
            $this->profitRatiosService->getAll()
        );
    }

    public function updateAll(Request $request)
    {
        $data = $request->validate([
            'ratios' => ['required', 'array'],
            'ratios.*.id' => ['required', 'exists:profit_ratios,id'],
            'ratios.*.value' => ['required', 'numeric', 'min:0'],
        ]);

        $this->profitRatiosService->updateAll($data['ratios']);

        return $this->success('تم تحديث جميع نسب الأرباح بنجاح');
    }
}
