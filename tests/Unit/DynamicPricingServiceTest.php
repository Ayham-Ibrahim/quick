<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProfitRatios;
use App\Services\Pricing\DynamicPricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DynamicPricingServiceTest extends TestCase
{
    use RefreshDatabase;

    private DynamicPricingService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(DynamicPricingService::class);

        ProfitRatios::create([
            'tag' => ProfitRatios::TAG_EXCHANGE_RATE,
            'ratio_name' => 'سعر صرف الدولار',
            'value' => 15000,
        ]);
    }

    /** @test */
    public function it_derives_base_price_usd_from_manual_product_price(): void
    {
        $payload = $this->service->prepareProductPricingPayload([
            'current_price' => 45000,
            'sync_enabled' => true,
        ], true);

        $this->assertSame(45000.0, $payload['current_price']);
        $this->assertSame(3.0, $payload['base_price_usd']);
        $this->assertTrue($payload['sync_enabled']);
    }

    /** @test */
    public function it_keeps_existing_base_price_when_synced_variant_updates_without_manual_price_change(): void
    {
        $variant = new ProductVariant([
            'price' => 47500,
            'base_price_usd' => 3,
        ]);

        $payload = $this->service->prepareVariantPricingPayload([
            'stock_quantity' => 9,
        ], true, true, $variant);

        $this->assertSame(47500.0, $payload['price']);
        $this->assertSame(3.0, $payload['base_price_usd']);
    }

    /** @test */
    public function it_keeps_existing_base_price_when_synced_product_updates_without_manual_price_change(): void
    {
        $product = new Product([
            'current_price' => 47500,
            'base_price_usd' => 3,
            'sync_enabled' => true,
        ]);

        $payload = $this->service->prepareProductPricingPayload([
            'quantity' => 9,
        ], true, $product);

        $this->assertSame(47500.0, $payload['current_price']);
        $this->assertSame(3.0, $payload['base_price_usd']);
        $this->assertTrue($payload['sync_enabled']);
    }
}