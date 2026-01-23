<?php

namespace App\Http\Controllers;

use App\Services\HomeService;
use Illuminate\Http\Request;

class UserHomePageController extends Controller
{
    public function __construct(protected HomeService $homeService) {}

    /**
     * عروض الإدارة - المنتجات التي عليها كوبونات نشطة
     * GET /api/home/admin-offers
     */
    public function adminOffers(Request $request)
    {
        $limit = $request->input('limit', 20);
        $data = $this->homeService->getAdminOffers($limit);

        return $this->success($data, 'عروض الإدارة');
    }

    /**
     * عروض المتاجر - المنتجات المخفضة (previous_price > current_price)
     * GET /api/home/store-offers
     */
    public function storeOffers(Request $request)
    {
        $limit = $request->input('limit', 20);
        $data = $this->homeService->getStoreOffers($limit);

        return $this->success($data, 'عروض المتاجر');
    }
}
