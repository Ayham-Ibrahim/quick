<?php

namespace App\Services;

use App\Models\Ads;
use App\Services\Service;
use Illuminate\Support\Facades\Storage;

class AdService extends Service
{
    public function storeAd($validated)
    {
        try {
            $ad = new Ads();

            if (isset($validated['ad_image_url'])) {
                $path = $validated['ad_image_url']->store('ads', 'public');
                $ad->ad_image_url = $path;
            }

            $ad->save();

            return $ad;
        } catch (\Exception $e) {
            $this->throwExceptionJson(
                'حدث خطأ أثناء إنشاء الإعلان',
                500,
                $e->getMessage()
            );
        }
    }

    public function deleteAd(Ads $ad)
    {
        try {
            if ($ad->ad_image_url && Storage::disk('public')->exists($ad->ad_image_url)) {
                Storage::disk('public')->delete($ad->ad_image_url);
            }

            $ad->delete();

            return true;
        } catch (\Exception $e) {
            $this->throwExceptionJson(
                'حدث خطأ أثناء حذف الإعلان',
                500,
                $e->getMessage()
            );
        }
    }
}
