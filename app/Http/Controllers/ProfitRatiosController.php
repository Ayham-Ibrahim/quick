<?php

namespace App\Http\Controllers;

use App\Models\ProfitRatios;
use App\Services\ProfitRatiosService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ProfitRatiosController extends Controller
{
    protected $profitRatiosService;

    public function __construct(ProfitRatiosService $profitRatiosService)
    {
        $this->profitRatiosService = $profitRatiosService;
    }

    public function index()
    {
        if(!Auth::user()->is_admin) {
            return $this->error('غير مصرح لك بالوصول إلى هذه البيانات', 403);
        }
        $data = ProfitRatios::all();
        return $this->success($data);
    }

    /**
     * Update all profit ratios at once.
     *
     * Expected request format:
     * ratios => [
     *   ['id' => 1, 'value' => 10],
     *   ['id' => 2, 'value' => 5],
     * ]
     */
    public function updateAll(Request $request)
    {
        if(!Auth::user()->is_admin) {
            return $this->error('غير مصرح لك بالوصول إلى هذه البيانات', 403);
        }
        $data = $request->validate([
            'ratios' => ['required', 'array'],
            'ratios.*.id' => ['required', 'exists:profit_ratios,id'],
            'ratios.*.value' => ['required', 'numeric', 'min:0'],
        ]);

        DB::transaction(function () use ($data) {
            foreach ($data['ratios'] as $ratio) {
                ProfitRatios::where('id', $ratio['id'])
                    ->update(['value' => $ratio['value']]);
            }
        });

        return $this->success( 'تم تحديث جميع نسب الأرباح بنجاح');
    }
}
