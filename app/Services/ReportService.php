<?php

namespace App\Services;

use App\Models\Driver;
use App\Models\Product;
use App\Models\Store;
use App\Models\UserManagement\Provider;
use App\Models\UserManagement\User;

class ReportService extends Service
{
    // احصائيات عامة
    public function staticsReport()
    {
        $totalUsers = User::count();
        $totalStores = Store::count();
        $totalProducts = Product::count();
        $totalProviders = Provider::count();
        $totalDrivers = Driver::count();

        return [
            'total_users' => $totalUsers,
            'total_stores' => $totalStores,
            'total_products' => $totalProducts,
            'total_providers' => $totalProviders,
            'total_drivers' => $totalDrivers,
            // 'latest_products' => $this->latestProducts(),
            'latest_products' => Product::with('mainProductImage')
                ->accepted()
                ->latest()
                ->take(10)
                ->get([
                    'id',
                    'name',
                    'description',
                    'current_price',
                    'quantity',
                ]),
        ];
    }

    // private function latestProducts()
    // {
    //     return Product::withCount('orderItems as sales_count')
    //         ->accepted()
    //         ->latest()
    //         ->take(10)
    //         ->get([
    //             'id',
    //             'name',
    //             'description',
    //             'current_price',
    //             'quantity',
    //             'sales_count'
    //         ]);
    // }
}
