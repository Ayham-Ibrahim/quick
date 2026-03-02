<?php

namespace Tests\Unit;

use App\Models\Driver;
use App\Models\ProfitRatios;
use App\Services\CustomOrder\CustomOrderService;
use App\Services\Geofencing\GeofencingService;
use App\Services\NotificationService;
use App\Services\AdminProfitService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Collections\Collection;
use Tests\TestCase;

class CustomOrderNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_drivers_are_notified_when_custom_order_created()
    {
        // prepare a driver that will be returned by the geofencing mock
        $driver = Driver::create([
            'driver_name' => 'Test Driver',
            'phone' => '0912345678',
            'password' => Hash::make('secret'),
            'is_active' => true,
            'current_lat' => 0,
            'current_lng' => 0,
        ]);

        // ensure pricing exists
        ProfitRatios::create(['tag' => 'km_price', 'value' => 1000]);

        // create mocks
        $geofencing = \Mockery::mock(GeofencingService::class);
        $geofencing->shouldReceive('getEligibleDriversForCustomOrder')
            ->once()
            ->andReturn(Collection::make([$driver]));

        $notification = \Mockery::mock(NotificationService::class);
        $notification->shouldReceive('notifyUserCustomOrderCreated')->once();
        $notification->shouldReceive('notifyDriversNewCustomOrder')
            ->once()
            ->withArgs(fn($drivers, $order) => $drivers->contains($driver));

        $adminProfit = app(AdminProfitService::class);

        $service = new CustomOrderService($notification, $geofencing, $adminProfit);

        $data = [
            'delivery_fee' => 0,
            'distance_km' => 1,
            'delivery_address' => 'somewhere',
            'delivery_lat' => 0,
            'delivery_lng' => 0,
            'is_immediate' => true,
            'items' => [
                [
                    'description' => 'foo',
                    'pickup_address' => 'bar',
                    'pickup_lat' => 0,
                    'pickup_lng' => 0,
                ],
            ],
        ];

        $order = $service->createOrder($data);

        $this->assertDatabaseHas('custom_orders', ['id' => $order->id]);
    }
}
