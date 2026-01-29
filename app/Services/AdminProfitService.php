<?php

namespace App\Services;

use App\Models\AdminProfit;
use App\Models\Driver;
use App\Models\Order;
use App\Models\CustomOrder;
use App\Models\ProfitRatios;
use App\Models\UserManagement\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdminProfitService extends Service
{
    /**
     * Get the admin user (first admin in the system)
     */
    private function getAdmin(): ?User
    {
        return User::where('is_admin', true)->first();
    }

    /**
     * Add profit to admin wallet
     */
    private function addToAdminWallet(float $amount): void
    {
        $admin = $this->getAdmin();
        if ($admin && $admin->wallet) {
            $admin->wallet->increment('balance', $amount);
            
            Log::info("Admin wallet credited", [
                'admin_id' => $admin->id,
                'amount' => $amount,
                'new_balance' => $admin->wallet->fresh()->balance,
            ]);
        }
    }

    /**
     * Process driver delivery profit
     * Deducts profit from driver wallet and adds it to admin wallet
     * 
     * @param Driver $driver The driver who completed the delivery
     * @param string $orderType 'order' or 'custom_order'
     * @param int $orderId The order ID
     * @param float $deliveryFee The delivery fee charged
     * @return float The profit amount deducted
     */
    public function processDriverDeliveryProfit(
        Driver $driver,
        string $orderType,
        int $orderId,
        float $deliveryFee
    ): float {
        // Get profit rate based on vehicle type
        $profitTag = $this->getDriverProfitTag($driver);
        $profitPercentage = (float) ProfitRatios::getValueByTag($profitTag);
        
        // Calculate profit amount
        $profitAmount = ($deliveryFee * $profitPercentage) / 100;
        
        if ($profitAmount <= 0) {
            return 0;
        }

        return DB::transaction(function () use ($driver, $orderType, $orderId, $profitAmount, $profitPercentage) {
            // Deduct from driver wallet
            $wallet = $driver->wallet;
            if ($wallet) {
                $wallet->decrement('balance', $profitAmount);
                
                Log::info("Driver profit deducted", [
                    'driver_id' => $driver->id,
                    'order_type' => $orderType,
                    'order_id' => $orderId,
                    'profit_amount' => $profitAmount,
                    'wallet_balance' => $wallet->fresh()->balance,
                ]);
            }

            // Add to admin wallet
            $this->addToAdminWallet($profitAmount);

            // Record the profit
            AdminProfit::recordDriverProfit(
                $driver->id,
                $orderType,
                $orderId,
                $profitAmount,
                "نسبة ربح " . $profitPercentage . "% من توصيل"
            );

            return $profitAmount;
        });
    }

    /**
     * Process store order profit
     * Records the profit from store order (deduction happens at checkout)
     * 
     * @param int $storeId The store ID
     * @param int $orderId The order ID
     * @param float $orderSubtotal The store's portion of the order subtotal
     * @return float The profit amount
     */
    public function processStoreOrderProfit(
        int $storeId,
        int $orderId,
        float $orderSubtotal
    ): float {
        $profitPercentage = (float) ProfitRatios::getValueByTag('order_profit_percentage');
        $profitAmount = ($orderSubtotal * $profitPercentage) / 100;
        
        if ($profitAmount <= 0) {
            return 0;
        }

        // Record the profit
        AdminProfit::recordStoreProfit(
            $storeId,
            $orderId,
            $profitAmount,
            "نسبة ربح " . $profitPercentage . "% من طلب"
        );

        Log::info("Store profit recorded", [
            'store_id' => $storeId,
            'order_id' => $orderId,
            'profit_amount' => $profitAmount,
        ]);

        return $profitAmount;
    }

    /**
     * Get the profit tag based on driver's vehicle type
     */
    private function getDriverProfitTag(Driver $driver): string
    {
        $vehicleType = $driver->vehicleType?->type ?? 'motorbike';
        
        // Map vehicle type to profit tag
        $tagMap = [
            'bike' => 'delivery_profit_per_ride_bike',
            'bicycle' => 'delivery_profit_per_ride_bike',
            'دراجة هوائية' => 'delivery_profit_per_ride_bike',
            'motorbike' => 'delivery_profit_per_ride_motorbike',
            'motorcycle' => 'delivery_profit_per_ride_motorbike',
            'دراجة نارية' => 'delivery_profit_per_ride_motorbike',
        ];

        return $tagMap[strtolower($vehicleType)] ?? 'delivery_profit_per_ride_motorbike';
    }

    /**
     * Get financial statistics for admin dashboard
     */
    public function getFinancialStatistics(): array
    {
        // Total orders value (delivered only)
        $totalOrdersValue = Order::where('status', Order::STATUS_DELIVERED)->sum('total');
        // Custom orders do not have a `total_cost` column; use `delivery_fee` as the cost metric
        $totalCustomOrdersValue = CustomOrder::where('status', CustomOrder::STATUS_DELIVERED)->sum('delivery_fee');
        
        $totalOrdersAmount = (float) $totalOrdersValue + (float) $totalCustomOrdersValue;

        // Profits from stores
        $profitFromStores = AdminProfit::getTotalStoreProfits();

        // Profits from drivers
        $profitFromDrivers = AdminProfit::getTotalDriverProfits();

        // Total profits
        $totalProfits = $profitFromStores + $profitFromDrivers;

        return [
            'total_orders_amount' => round($totalOrdersAmount, 2),
            'profit_from_stores' => round($profitFromStores, 2),
            'profit_from_drivers' => round($profitFromDrivers, 2),
            'total_profits' => round($totalProfits, 2),
        ];
    }
}
