<?php

namespace App\Http\Controllers;

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
     * Note: FCM token is intentionally kept to preserve push notifications.
     *
     * @param RegisterDeviceRequest $request
     * @return JsonResponse
     */
    public function removeUserDevice(RegisterDeviceRequest $request): JsonResponse
    {
        // لا نحذف الـ FCM token حتى يستمر المستخدم بتلقي الإشعارات بعد تسجيل الخروج
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
     * Note: FCM token is intentionally kept to preserve push notifications.
     *
     * @return JsonResponse
     */
    public function removeDriverDevice(): JsonResponse
    {
        // لا نحذف الـ FCM token حتى يستمر السائق بتلقي الإشعارات بعد تسجيل الخروج
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
     * Note: FCM token is intentionally kept to preserve push notifications.
     *
     * @return JsonResponse
     */
    public function removeProviderDevice(): JsonResponse
    {
        // لا نحذف الـ FCM token حتى يستمر المزود بتلقي الإشعارات بعد تسجيل الخروج
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
     * Note: FCM token is intentionally kept to preserve push notifications.
     *
     * @return JsonResponse
     */
    public function removeStoreDevice(): JsonResponse
    {
        // لا نحذف الـ FCM token حتى يستمر المتجر بتلقي الإشعارات بعد تسجيل الخروج
        return $this->success(null, 'تم إلغاء تسجيل الجهاز');
    }
}
