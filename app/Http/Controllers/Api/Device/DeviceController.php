<?php

namespace App\Http\Controllers\Api\Device;

use App\Http\Controllers\Controller;
use App\Http\Requests\Device\RegisterDeviceRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

/**
 * Device Controller
 * 
 * Handles FCM token registration for push notifications.
 * Supports multiple owner types: User, Driver, Provider, Store
 */
class DeviceController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | User Device Management (Multi-device support)
    |--------------------------------------------------------------------------
    */

    /**
     * Register FCM token for authenticated user.
     * Users can have multiple devices.
     *
     * @param RegisterDeviceRequest $request
     * @return JsonResponse
     */
    public function registerUserDevice(RegisterDeviceRequest $request): JsonResponse
    {
        $user = Auth::guard('api')->user();

        $device = $user->registerDevice($request->fcm_token);

        return $this->success([
            'device_id' => $device->id,
        ], 'تم تسجيل الجهاز بنجاح');
    }

    /**
     * Remove FCM token for authenticated user (logout from device).
     *
     * @param RegisterDeviceRequest $request
     * @return JsonResponse
     */
    public function removeUserDevice(RegisterDeviceRequest $request): JsonResponse
    {
        $user = Auth::guard('api')->user();

        \App\Models\Device::removeByToken($user, $request->fcm_token);

        return $this->success(null, 'تم إلغاء تسجيل الجهاز');
    }

    /*
    |--------------------------------------------------------------------------
    | Driver Device Management (Single-device only)
    |--------------------------------------------------------------------------
    */

    /**
     * Register FCM token for authenticated driver.
     * Drivers only support single device - previous device will be replaced.
     *
     * @param RegisterDeviceRequest $request
     * @return JsonResponse
     */
    public function registerDriverDevice(RegisterDeviceRequest $request): JsonResponse
    {
        $driver = Auth::guard('driver')->user();

        $device = $driver->registerDevice($request->fcm_token);

        return $this->success([
            'device_id' => $device->id,
        ], 'تم تسجيل الجهاز بنجاح');
    }

    /**
     * Remove FCM token for authenticated driver.
     *
     * @return JsonResponse
     */
    public function removeDriverDevice(): JsonResponse
    {
        $driver = Auth::guard('driver')->user();

        \App\Models\Device::removeAllDevices($driver);

        return $this->success(null, 'تم إلغاء تسجيل الجهاز');
    }

    /*
    |--------------------------------------------------------------------------
    | Provider Device Management (Single-device only)
    |--------------------------------------------------------------------------
    */

    /**
     * Register FCM token for authenticated provider.
     * Providers only support single device - previous device will be replaced.
     *
     * @param RegisterDeviceRequest $request
     * @return JsonResponse
     */
    public function registerProviderDevice(RegisterDeviceRequest $request): JsonResponse
    {
        $provider = Auth::guard('provider')->user();

        $device = $provider->registerDevice($request->fcm_token);

        return $this->success([
            'device_id' => $device->id,
        ], 'تم تسجيل الجهاز بنجاح');
    }

    /**
     * Remove FCM token for authenticated provider.
     *
     * @return JsonResponse
     */
    public function removeProviderDevice(): JsonResponse
    {
        $provider = Auth::guard('provider')->user();

        \App\Models\Device::removeAllDevices($provider);

        return $this->success(null, 'تم إلغاء تسجيل الجهاز');
    }

    /*
    |--------------------------------------------------------------------------
    | Store Device Management (Single-device only)
    |--------------------------------------------------------------------------
    */

    /**
     * Register FCM token for authenticated store.
     * Stores only support single device - previous device will be replaced.
     *
     * @param RegisterDeviceRequest $request
     * @return JsonResponse
     */
    public function registerStoreDevice(RegisterDeviceRequest $request): JsonResponse
    {
        $store = Auth::guard('store')->user();

        $device = $store->registerDevice($request->fcm_token);

        return $this->success([
            'device_id' => $device->id,
        ], 'تم تسجيل الجهاز بنجاح');
    }

    /**
     * Remove FCM token for authenticated store.
     *
     * @return JsonResponse
     */
    public function removeStoreDevice(): JsonResponse
    {
        $store = Auth::guard('store')->user();

        \App\Models\Device::removeAllDevices($store);

        return $this->success(null, 'تم إلغاء تسجيل الجهاز');
    }
}
