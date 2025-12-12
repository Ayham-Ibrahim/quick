<?php

namespace App\Http\Controllers;

use App\Http\Requests\AdRequest;
use App\Models\Ads;
use App\Services\AdService;
use App\Http\Controllers\Controller;

class AdsController extends Controller
{
    protected $adService;

    public function __construct(AdService $adService)
    {
        $this->adService = $adService;
    }

    public function index()
    {
        $ads = Ads::latest()->get();
        return $this->success($ads, 'تم جلب الإعلانات بنجاح');
    }

    public function store(AdRequest $request)
    {
        $validated = $request->validated();

        $ad = $this->adService->storeAd($validated);

        return $this->success($ad, 'تم إنشاء الإعلان بنجاح', 201);
    }

    public function show(Ads $ad)
    {
        return $this->success($ad, 'تم جلب الإعلان بنجاح');
    }

    public function destroy(Ads $ad)
    {
        $this->adService->deleteAd($ad);

        return $this->success(null, 'تم حذف الإعلان بنجاح');
    }
}
