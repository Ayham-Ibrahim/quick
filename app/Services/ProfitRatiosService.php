<?php

namespace App\Services;

use App\Models\ProfitRatios;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Throwable;

class ProfitRatiosService extends Service
{
    /**
     * Get all profit ratios (Admin only).
     */
    public function getAll()
    {
        if (!Auth::user()?->is_admin) {
            $this->throwExceptionJson('غير مصرح لك بالوصول إلى هذه البيانات', 403);
        }

        return ProfitRatios::all();
    }

    /**
     * Bulk update profit ratios (Admin only).
     *
     * @param array $ratios
     */
    public function updateAll(array $ratios): void
    {
        if (!Auth::user()?->is_admin) {
            $this->throwExceptionJson('غير مصرح لك بتعديل هذه البيانات', 403);
        }

        try {
            DB::transaction(function () use ($ratios) {
                foreach ($ratios as $ratio) {
                    ProfitRatios::where('id', $ratio['id'])
                        ->update([
                            'value' => $ratio['value'],
                        ]);
                }
            });
        } catch (Throwable $e) {
            $this->throwExceptionJson(
                'فشل تحديث نسب الأرباح',
                500,
                config('app.debug') ? $e->getMessage() : null
            );
        }
    }
}
