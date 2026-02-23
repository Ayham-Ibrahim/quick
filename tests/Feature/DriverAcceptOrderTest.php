<?php

namespace Tests\Feature;

use App\Models\Driver;
use App\Models\Order;
use App\Models\CustomOrder;
use App\Models\UserManagement\User;
use App\Helpers\WalletHelper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DriverAcceptOrderTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Helper to create a driver with a wallet balance and specified online state.
     */
    protected function createDriver(array $overrides = []): Driver
    {
        $driver = Driver::create(array_merge([
            'driver_name' => 'Test Driver',
            'phone' => '09'.rand(10000000, 99999999),
            'password' => Hash::make('password'),
            'driver_image' => 'dummy.jpg',
            'front_id_image' => 'id1.jpg',
            'back_id_image' => 'id2.jpg',
            'v_location' => '0.0',
            'h_location' => '0.0',
            'is_active' => true,
            'is_online' => false,
        ], $overrides));

        // ensure wallet exists with enough balance
        if (! $driver->wallet) {
            $driver->wallet()->create([
                'wallet_code' => WalletHelper::generateUniqueWalletCode(),
                'balance' => 1000,
            ]);
        } else {
            $driver->wallet->update(['balance' => 1000]);
        }

        return $driver;
    }

    /**
     * Create a basic pending order for a given user.
     */
    protected function createOrder(User $user): Order
    {
        return Order::create([
            'user_id' => $user->id,
            'subtotal' => 0,
            'discount_amount' => 0,
            'delivery_fee' => 0,
            'total' => 0,
            'status' => Order::STATUS_PENDING,
            'delivery_address' => 'Test address',
            'is_immediate_delivery' => true,
        ]);
    }

    /**
     * Create a basic pending custom order for a given user.
     */
    protected function createCustomOrder(User $user): CustomOrder
    {
        return CustomOrder::create([
            'user_id' => $user->id,
            'status' => CustomOrder::STATUS_PENDING,
            'delivery_fee' => 0,
            'distance_km' => 0,
            'delivery_address' => 'Test address',
            'is_immediate' => true,
        ]);
    }

    public function test_offline_driver_cannot_accept_regular_order()
    {
        $driver = $this->createDriver(['is_online' => false]);
        $user = User::factory()->create();
        $order = $this->createOrder($user);

        $this->actingAs($driver, 'driver')
            ->postJson("/api/driver/orders/{$order->id}/accept")
            ->assertStatus(400)
            ->assertJson([
                'status' => 'error',
                'message' => 'يجب أن تكون متصلاً لتقبل الطلب',
            ]);
    }

    public function test_online_driver_can_accept_regular_order()
    {
        $driver = $this->createDriver(['is_online' => true]);
        $user = User::factory()->create();
        $order = $this->createOrder($user);

        $this->actingAs($driver, 'driver')
            ->postJson("/api/driver/orders/{$order->id}/accept")
            ->assertStatus(200)
            ->assertJsonFragment(['success' => true]);
    }

    public function test_offline_driver_cannot_accept_custom_order()
    {
        $driver = $this->createDriver(['is_online' => false]);
        $user = User::factory()->create();
        $order = $this->createCustomOrder($user);

        $this->actingAs($driver, 'driver')
            ->postJson("/api/driver/custom-orders/{$order->id}/accept")
            ->assertStatus(400)
            ->assertJson([
                'status' => 'error',
                'message' => 'يجب أن تكون متصلاً لتقبل الطلب',
            ]);
    }
}
