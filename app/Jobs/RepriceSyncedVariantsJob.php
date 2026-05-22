<?php

namespace App\Jobs;

use App\Models\Product;
use App\Services\Pricing\DynamicPricingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;

class RepriceSyncedVariantsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [30, 60, 120];

    public int $timeout = 120;

    private const CHUNK_SIZE = 500;

    public function __construct(public float $exchangeRate)
    {
    }

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('reprice-synced-variants'))->releaseAfter(10)->expireAfter(600),
        ];
    }

    public function handle(DynamicPricingService $dynamicPricingService): void
    {
        if (!$dynamicPricingService->isExchangeRateCurrent($this->exchangeRate)) {
            return;
        }

        Product::query()
            ->where('sync_enabled', true)
            ->with(['variants:id,product_id,price,base_price_usd'])
            ->orderBy('id')
            ->chunkById(self::CHUNK_SIZE, function ($products) use ($dynamicPricingService) {
                if (!$dynamicPricingService->isExchangeRateCurrent($this->exchangeRate)) {
                    return false;
                }

                foreach ($products as $product) {
                    $dynamicPricingService->repriceProduct($product, $this->exchangeRate);

                    foreach ($product->variants as $variant) {
                        $dynamicPricingService->repriceVariant($variant, $this->exchangeRate);
                    }
                }

                return true;
            });
    }
}