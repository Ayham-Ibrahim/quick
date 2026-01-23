<?php

namespace App\Services;

use App\Models\Driver;
use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use App\Models\UserManagement\Provider;
use App\Models\UserManagement\User;
use Illuminate\Support\Facades\DB;

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
            'top_selling_products' => $this->getTopSellingProducts(10),
        ];
    }

    /**
     * Get top selling products with sales count and remaining stock
     * 
     * @param int $limit Number of products to return
     * @return \Illuminate\Support\Collection
     */
    public function getTopSellingProducts(int $limit = 10)
    {
        return Product::query()
            ->select([
                'products.id',
                'products.name',
                'products.description',
                'products.current_price',
                'products.quantity as remaining_stock',
            ])
            ->withCount([
                'orderItems as total_sales' => function ($query) {
                    // Only count from delivered orders
                    $query->select(DB::raw('COALESCE(SUM(order_items.quantity), 0)'))
                        ->join('orders', 'order_items.order_id', '=', 'orders.id')
                        ->where('orders.status', Order::STATUS_DELIVERED);
                }
            ])
            ->with('mainProductImage')
            ->withAvg('ratings', 'rating')
            ->withCount('ratings')
            ->accepted()
            ->orderByDesc('total_sales')
            ->take($limit)
            ->get()
            ->map(function ($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'description' => $product->description,
                    'current_price' => $product->current_price,
                    'image' => $product->mainProductImage?->image ?? null,
                    'total_sales' => (int) $product->total_sales,
                    'remaining_stock' => (int) $product->remaining_stock,
                    'average_rating' => round($product->ratings_avg_rating ?? 0, 1),
                    'ratings_count' => (int) $product->ratings_count,
                ];
            });
    }
}
