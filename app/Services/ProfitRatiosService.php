<?php

namespace App\Services;

use App\Jobs\RepriceSyncedVariantsJob;
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
            $exchangeRateToDispatch = null;
            $incomingRatios = collect($ratios)->keyBy('id');
            $currentRatios = ProfitRatios::query()
                ->whereIn('id', $incomingRatios->keys())
                ->get(['id', 'tag', 'value'])
                ->keyBy('id');

            DB::transaction(function () use ($ratios) {
                foreach ($ratios as $ratio) {
                    ProfitRatios::where('id', $ratio['id'])
                        ->update([
                            'value' => $ratio['value'],
                        ]);
                }
            });

            $exchangeRateRow = $currentRatios->firstWhere('tag', ProfitRatios::TAG_EXCHANGE_RATE);
            $incomingExchangeRate = $exchangeRateRow
                ? (float) ($incomingRatios->get($exchangeRateRow->id)['value'] ?? 0)
                : null;

            if (
                $exchangeRateRow
                && $incomingExchangeRate !== null
                && $incomingExchangeRate > 0
                && (float) $exchangeRateRow->value !== $incomingExchangeRate
            ) {
                $exchangeRateToDispatch = $incomingExchangeRate;
            }

            if ($exchangeRateToDispatch !== null) {
                RepriceSyncedVariantsJob::dispatchSync($exchangeRateToDispatch);
            }
        } catch (Throwable $e) {
            $this->throwExceptionJson(
                'فشل تحديث نسب الأرباح',
                500,
                config('app.debug') ? $e->getMessage() : null
            );
        }
    }
}
