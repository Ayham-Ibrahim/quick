<?php

namespace App\Services\UserManagementServices;

use App\Models\UserManagement\Provider;
use App\Services\Service;
use Illuminate\Support\Facades\Hash;

class ProviderService extends Service
{
    /**
     * Create a new provider account
     */
    public function createProvider(array $data)
    {
        try {
            $data['password'] = Hash::make($data['password']);

            return Provider::create($data);
        } catch (\Exception $e) {
            $this->throwExceptionJson(
                'حدث خطأ أثناء إنشاء حساب المزود',
                500,
                $e->getMessage()
            );
        }
    }

    /**
     * Update provider data
     */
    public function updateProvider(Provider $provider, array $data)
    {
        try {
            $provider->provider_name = $data['provider_name'] ??  $provider->provider_name;
            $provider->market_name = $data['market_name'] ??  $provider->market_name;
            $provider->v_location = $data['v_location'] ??  $provider->v_location;
            $provider->h_location = $data['h_location'] ??  $provider->h_location;
            $provider->phone = $data['phone'] ??  $provider->phone;
            $provider->city = $data['city'] ??  $provider->city;
            $provider->save();

            return $provider;
        } catch (\Exception $e) {
            $this->throwExceptionJson(
                'حدث خطأ أثناء تحديث حساب المزود',
                500,
                $e->getMessage()
            );
        }
    }

    /**
     * Delete provider
     */
    public function deleteProvider(Provider $provider)
    {
        try {
            $provider->delete();
            return true;
        } catch (\Exception $e) {
            $this->throwExceptionJson(
                'حدث خطأ أثناء حذف حساب المزود',
                500,
                $e->getMessage()
            );
        }
    }
}
