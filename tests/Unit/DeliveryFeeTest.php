<?php

namespace Tests\Unit;

use App\Services\CustomOrder\CustomOrderService;
use App\Models\ProfitRatios;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliveryFeeTest extends TestCase
{
    use RefreshDatabase;

    protected CustomOrderService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CustomOrderService(
            app('App\Services\NotificationService'),
            app('App\Services\Geofencing\GeofencingService'),
            app('App\Services\AdminProfitService')
        );

        // ensure km_price exists
        ProfitRatios::create(['tag' => 'km_price', 'value' => 10000]);
    }

    /** @test */
    public function it_calculates_exact_fee_without_rounding()
    {
        // 4.4 km should give 44000 (not 40000)
        $this->assertSame(44000.0, $this->service->calculateDeliveryFee(4.4));

        // 4.8 km should give 48000 (not 50000)
        $this->assertSame(48000.0, $this->service->calculateDeliveryFee(4.8));

        // 5.5 km -> 55000
        $this->assertSame(55000.0, $this->service->calculateDeliveryFee(5.5));
    }

    /** @test */
    public function it_handles_fractional_prices()
    {
        // change price to a fractional value
        ProfitRatios::where('tag', 'km_price')->update(['value' => 1234.56]);

        $this->assertSame(4.4 * 1234.56, $this->service->calculateDeliveryFee(4.4));
    }
}
