<?php

namespace App\Services;

use App\Jobs\SendDriverApproachingNotification;
use App\Models\CustomOrder;
use App\Models\Driver;
use App\Models\Order;
use Illuminate\Support\Facades\Log;

/**
 * خدمة فحص اقتراب السائق من العميل (محسّنة للأداء)
 * 
 * التحسينات:
 * 1. استخدام Bounding Box للتصفية السريعة قبل حساب المسافة الدقيقة
 * 2. حساب المسافة في SQL بدلاً من PHP (أسرع)
 * 3. استعلام واحد بدلاً من جلب كل الطلبات ثم التكرار عليها
 * 4. إرسال الإشعار عبر Queue (لا يؤخر response السائق)
 */
class DriverProximityService
{
    /**
     * المسافة التي يُعتبر فيها السائق "قريباً" (بالمتر)
     */
    const APPROACHING_DISTANCE_METERS = 500;

    /**
     * درجة واحدة latitude ≈ 111 كم
     * 500m ≈ 0.0045 درجة (نستخدم 0.006 كـ buffer)
     */
    const LAT_DEGREE_BUFFER = 0.006;

    /**
     * درجة واحدة longitude تختلف حسب latitude
     * نستخدم قيمة آمنة للمنطقة العربية (~0.006)
     */
    const LNG_DEGREE_BUFFER = 0.006;

    /**
     * فحص اقتراب السائق من نقاط التسليم وإرسال الإشعارات
     * 
     * @param Driver $driver
     * @return void
     */
    public function checkAndNotifyApproaching(Driver $driver): void
    {
        // فحص أولي: هل السائق لديه موقع؟
        if (!$driver->current_lat || !$driver->current_lng) {
            return;
        }

        $driverLat = (float) $driver->current_lat;
        $driverLng = (float) $driver->current_lng;

        // فحص الطلبات العادية
        $this->checkOrdersOptimized($driver->id, $driverLat, $driverLng);

        // فحص الطلبات الخاصة
        $this->checkCustomOrdersOptimized($driver->id, $driverLat, $driverLng);
    }

    /**
     * فحص الطلبات العادية باستخدام استعلام محسّن
     */
    private function checkOrdersOptimized(int $driverId, float $driverLat, float $driverLng): void
    {
        // استعلام محسّن:
        // 1. Bounding box للتصفية السريعة (index-friendly)
        // 2. حساب المسافة في SQL
        // 3. جلب الطلبات القريبة فقط
        $nearbyOrders = Order::where('driver_id', $driverId)
            ->where('status', Order::STATUS_SHIPPING)
            ->whereNull('driver_approaching_notified_at')
            ->whereNotNull('delivery_lat')
            ->whereNotNull('delivery_lng')
            // Bounding Box filter (سريع جداً مع index)
            ->whereBetween('delivery_lat', [
                $driverLat - self::LAT_DEGREE_BUFFER,
                $driverLat + self::LAT_DEGREE_BUFFER
            ])
            ->whereBetween('delivery_lng', [
                $driverLng - self::LNG_DEGREE_BUFFER,
                $driverLng + self::LNG_DEGREE_BUFFER
            ])
            // حساب المسافة الدقيقة في SQL (Haversine)
            ->whereRaw('
                (6371000 * acos(
                    cos(radians(?)) * cos(radians(delivery_lat)) *
                    cos(radians(delivery_lng) - radians(?)) +
                    sin(radians(?)) * sin(radians(delivery_lat))
                )) <= ?
            ', [$driverLat, $driverLng, $driverLat, self::APPROACHING_DISTANCE_METERS])
            ->with('user')
            ->get();

        foreach ($nearbyOrders as $order) {
            $this->sendApproachingNotification($order, 'regular');
        }
    }

    /**
     * فحص الطلبات الخاصة باستخدام استعلام محسّن
     */
    private function checkCustomOrdersOptimized(int $driverId, float $driverLat, float $driverLng): void
    {
        $nearbyOrders = CustomOrder::where('driver_id', $driverId)
            ->where('status', CustomOrder::STATUS_SHIPPING)
            ->whereNull('driver_approaching_notified_at')
            ->whereNotNull('delivery_lat')
            ->whereNotNull('delivery_lng')
            // Bounding Box filter
            ->whereBetween('delivery_lat', [
                $driverLat - self::LAT_DEGREE_BUFFER,
                $driverLat + self::LAT_DEGREE_BUFFER
            ])
            ->whereBetween('delivery_lng', [
                $driverLng - self::LNG_DEGREE_BUFFER,
                $driverLng + self::LNG_DEGREE_BUFFER
            ])
            // حساب المسافة الدقيقة في SQL
            ->whereRaw('
                (6371000 * acos(
                    cos(radians(?)) * cos(radians(delivery_lat)) *
                    cos(radians(delivery_lng) - radians(?)) +
                    sin(radians(?)) * sin(radians(delivery_lat))
                )) <= ?
            ', [$driverLat, $driverLng, $driverLat, self::APPROACHING_DISTANCE_METERS])
            ->with('user')
            ->get();

        foreach ($nearbyOrders as $order) {
            $this->sendApproachingNotification($order, 'custom');
        }
    }

    /**
     * إرسال إشعار الاقتراب وتحديث الطلب (عبر Queue)
     */
    private function sendApproachingNotification(Order|CustomOrder $order, string $orderType): void
    {
        // تحديث الطلب لمنع إرسال الإشعار مرة أخرى (يتم فوراً)
        $order->update(['driver_approaching_notified_at' => now()]);

        // إرسال الإشعار عبر Queue (لا يؤخر response السائق)
        SendDriverApproachingNotification::dispatch($orderType, $order->id)
            ->onQueue('notifications');

        Log::info("DriverProximityService: Queued approaching notification", [
            'order_type' => $orderType,
            'order_id' => $order->id,
            'driver_id' => $order->driver_id,
        ]);
    }
}
