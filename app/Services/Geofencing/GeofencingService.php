<?php

namespace App\Services\Geofencing;

use App\Models\Driver;
use App\Models\Order;
use App\Models\CustomOrder;
use App\Models\Store;
use App\Models\ProfitRatios;
use Illuminate\Support\Collection;
use Carbon\Carbon;

/**
 * خدمة النطاق الجغرافي التدريجي (Progressive Geofencing)
 * 
 * ═══════════════════════════════════════════════════════════════════════
 * آلية العمل:
 * ═══════════════════════════════════════════════════════════════════════
 * 
 * 1. عند إنشاء طلب، يتم حساب نقطة المركز (مركز ثقل المتاجر أو موقع التوصيل)
 * 2. يتم إرسال الإشعارات للسائقين تدريجياً حسب الزمن:
 *    - 0-2 دقيقة: نصف قطر 1 كم
 *    - 2-4 دقائق: نصف قطر 2 كم
 *    - 4-6 دقائق: نصف قطر 3 كم
 *    - 6-8 دقائق: نصف قطر 4 كم
 *    - 8-10 دقائق: نصف قطر 5 كم (الحد الأقصى)
 * 3. أول سائق يقبل → يتم إغلاق الطلبية فوراً
 * 
 * ═══════════════════════════════════════════════════════════════════════
 */
class GeofencingService
{
    /**
     * إعدادات النطاق الجغرافي التدريجي
     * [الحد الأقصى بالدقائق => نصف القطر بالكيلومتر]
     */
    const PROGRESSIVE_RADIUS_CONFIG = [
        2  => 1,   // 0-2 دقيقة: 1 كم
        4  => 2,   // 2-4 دقائق: 2 كم
        6  => 3,   // 4-6 دقائق: 3 كم
        8  => 4,   // 6-8 دقائق: 4 كم
        10 => 5,   // 8-10 دقائق: 5 كم (الحد الأقصى)
    ];

    /**
     * الحد الأقصى للمسافة بين أي متجرين في الطلب الواحد (كم)
     */
    const MAX_DISTANCE_BETWEEN_STORES_KM = 3;

    /**
     * مدة اعتبار السائق نشطاً (بالدقائق)
     */
    const DRIVER_ACTIVITY_TIMEOUT_MINUTES = 5;

    /**
     * حساب المسافة بين نقطتين باستخدام صيغة Haversine
     * 
     * @param string $lat1 خط العرض للنقطة الأولى
     * @param string $lng1 خط الطول للنقطة الأولى
     * @param float $lat2 خط العرض للنقطة الثانية
     * @param float $lng2 خط الطول للنقطة الثانية
     * @return float المسافة بالكيلومتر
     */
    public function calculateDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371; // نصف قطر الأرض بالكيلومتر

        $latDiff = deg2rad($lat2 - $lat1);
        $lngDiff = deg2rad($lng2 - $lng1);

        $a = sin($latDiff / 2) * sin($latDiff / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($lngDiff / 2) * sin($lngDiff / 2);
        
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    /**
     * الحصول على نصف القطر المناسب حسب الوقت المنقضي
     * 
     * @param Carbon $orderCreatedAt وقت إنشاء الطلب
     * @return float نصف القطر بالكيلومتر
     */
    public function getCurrentRadius(Carbon $orderCreatedAt): float
    {
        $minutesElapsed = now()->diffInMinutes($orderCreatedAt);
        
        foreach (self::PROGRESSIVE_RADIUS_CONFIG as $maxMinutes => $radiusKm) {
            if ($minutesElapsed < $maxMinutes) {
                return $radiusKm;
            }
        }
        
        // إرجاع الحد الأقصى
        return max(self::PROGRESSIVE_RADIUS_CONFIG);
    }

    /**
     * حساب مركز ثقل المتاجر في الطلب
     * 
     * @param Order $order الطلب
     * @return array ['lat' => float, 'lng' => float]
     */
    public function calculateOrderCentroid(Order $order): array
    {
        // جمع إحداثيات جميع المتاجر في الطلب
        $storeLocations = $order->items()
            ->with('store')
            ->get()
            ->pluck('store')
            ->unique('id')
            ->filter(fn($store) => $store && $store->v_location && $store->h_location)
            ->map(fn($store) => [
                'lat' => (float) $store->v_location,
                'lng' => (float) $store->h_location,
            ]);

        if ($storeLocations->isEmpty()) {
            // إذا لم تكن هناك متاجر، نستخدم موقع التوصيل
            return [
                'lat' => (float) $order->delivery_lat,
                'lng' => (float) $order->delivery_lng,
            ];
        }

        // حساب المتوسط (مركز الثقل)
        $avgLat = $storeLocations->avg('lat');
        $avgLng = $storeLocations->avg('lng');

        return ['lat' => $avgLat, 'lng' => $avgLng];
    }

    /**
     * حساب مركز ثقل الطلب الخاص
     * 
     * @param CustomOrder $order الطلب الخاص
     * @return array ['lat' => float, 'lng' => float]
     */
    public function calculateCustomOrderCentroid(CustomOrder $order): array
    {
        // جمع إحداثيات جميع نقاط الاستلام + موقع التوصيل
        $locations = $order->items()
            ->whereNotNull('pickup_lat')
            ->whereNotNull('pickup_lng')
            ->get()
            ->map(fn($item) => [
                'lat' => (float) $item->pickup_lat,
                'lng' => (float) $item->pickup_lng,
            ]);

        // إضافة موقع التوصيل
        if ($order->delivery_lat && $order->delivery_lng) {
            $locations->push([
                'lat' => (float) $order->delivery_lat,
                'lng' => (float) $order->delivery_lng,
            ]);
        }

        if ($locations->isEmpty()) {
            return ['lat' => 0, 'lng' => 0];
        }

        // حساب المتوسط (مركز الثقل)
        $avgLat = $locations->avg('lat');
        $avgLng = $locations->avg('lng');

        return ['lat' => $avgLat, 'lng' => $avgLng];
    }

    /**
     * التحقق من أن السائق ضمن النطاق الجغرافي
     * 
     * @param Driver $driver السائق
     * @param float $centerLat خط عرض المركز
     * @param float $centerLng خط طول المركز
     * @param float $radiusKm نصف القطر بالكيلومتر
     * @return bool
     */
    public function isDriverInRadius(Driver $driver, float $centerLat, float $centerLng, float $radiusKm): bool
    {
        if (!$driver->current_lat || !$driver->current_lng) {
            return false;
        }
    
        $distance = $this->calculateDistance(
            $driver->current_lat,
            $driver->current_lng,
            $centerLat,
            $centerLng
        );

        return $distance <= $radiusKm;
    }

    /**
     * التحقق من أن السائق نشط (متصل ومتفاعل)
     * 
     * @param Driver $driver السائق
     * @return bool
     */
    public function isDriverActive(Driver $driver): bool
    {
        // يجب أن يكون السائق:
        // 1. نشط (is_active = true)
        // 2. متصل (is_online = true)
        // 3. آخر نشاط خلال الـ 5 دقائق الأخيرة
        
        if (!$driver->is_active || !$driver->is_online) {
            return false;
        }

        if (!$driver->last_activity_at) {
            return false;
        }

        $minutesSinceActivity = now()->diffInMinutes($driver->last_activity_at);
        
        return $minutesSinceActivity <= self::DRIVER_ACTIVITY_TIMEOUT_MINUTES;
    }

    /**
     * الحصول على السائقين المؤهلين ضمن النطاق الجغرافي للطلب العادي
     * 
     * @param Order $order الطلب
     * @return Collection<Driver>
     */
    public function getEligibleDriversForOrder(Order $order): Collection
    {
        // حساب مركز الثقل
        $centroid = $this->calculateOrderCentroid($order);
        
        // الحصول على نصف القطر الحالي
        $radius = $this->getCurrentRadius($order->created_at);

        // نوع الطلب (فوري/مجدول)
        $isImmediate = $order->is_immediate_delivery;

        return Driver::query()
            ->where('is_active', true)
            ->where('is_online', true)
            ->whereNotNull('current_lat')
            ->whereNotNull('current_lng')
            ->get()
            ->filter(function (Driver $driver) use ($centroid, $radius, $isImmediate) {
                // التحقق من النشاط
                if (!$this->isDriverActive($driver)) {
                    return false;
                }

                // التحقق من النطاق الجغرافي
                if (!$this->isDriverInRadius($driver, $centroid['lat'], $centroid['lng'], $radius)) {
                    return false;
                }

                // التحقق من الأهلية (رصيد + طلبات نشطة)
                if ($isImmediate) {
                    return $driver->isEligibleForImmediateOrder();
                } else {
                    return $driver->isEligibleForScheduledOrder();
                }
            });
    }

    /**
     * الحصول على السائقين المؤهلين ضمن النطاق الجغرافي للطلب الخاص
     * 
     * @param CustomOrder $order الطلب الخاص
     * @return Collection<Driver>
     */
    public function getEligibleDriversForCustomOrder(CustomOrder $order): Collection
    {
        // حساب مركز الثقل
        $centroid = $this->calculateCustomOrderCentroid($order);
        
        // الحصول على نصف القطر الحالي
        $radius = $this->getCurrentRadius($order->created_at);

        // نوع الطلب (فوري/مجدول)
        $isImmediate = $order->is_immediate;

        return Driver::query()
            ->where('is_active', true)
            ->where('is_online', true)
            ->whereNotNull('current_lat')
            ->whereNotNull('current_lng')
            ->get()
            ->filter(function (Driver $driver) use ($centroid, $radius, $isImmediate) {
                // التحقق من النشاط
                if (!$this->isDriverActive($driver)) {
                    return false;
                }

                // التحقق من النطاق الجغرافي
                if (!$this->isDriverInRadius($driver, $centroid['lat'], $centroid['lng'], $radius)) {
                    return false;
                }

                // التحقق من الأهلية (رصيد + طلبات نشطة)
                if ($isImmediate) {
                    return $driver->isEligibleForImmediateCustomOrder();
                } else {
                    return $driver->isEligibleForScheduledOrder();
                }
            });
    }

    /**
     * التحقق من صلاحية المسافة بين المتاجر في الطلب
     * 
     * @param array $storeIds قائمة معرفات المتاجر
     * @return array ['valid' => bool, 'max_distance' => float, 'stores_pair' => array|null]
     */
    public function validateStoresDistance(array $storeIds): array
    {
        if (count($storeIds) <= 1) {
            return ['valid' => true, 'max_distance' => 0, 'stores_pair' => null];
        }

        $stores = Store::whereIn('id', $storeIds)
            ->whereNotNull('v_location')
            ->whereNotNull('h_location')
            ->get();

        $maxDistance = 0;
        $storesPair = null;

        // حساب المسافة بين كل زوج من المتاجر
        for ($i = 0; $i < $stores->count(); $i++) {
            for ($j = $i + 1; $j < $stores->count(); $j++) {
                $store1 = $stores[$i];
                $store2 = $stores[$j];

                $distance = $this->calculateDistance(
                    (float) $store1->v_location,
                    (float) $store1->h_location,
                    (float) $store2->v_location,
                    (float) $store2->h_location
                );

                if ($distance > $maxDistance) {
                    $maxDistance = $distance;
                    $storesPair = [
                        'store1' => ['id' => $store1->id, 'name' => $store1->store_name],
                        'store2' => ['id' => $store2->id, 'name' => $store2->store_name],
                    ];
                }
            }
        }

        $isValid = $maxDistance <= self::MAX_DISTANCE_BETWEEN_STORES_KM;

        return [
            'valid' => $isValid,
            'max_distance' => round($maxDistance, 2),
            'max_allowed' => self::MAX_DISTANCE_BETWEEN_STORES_KM,
            'stores_pair' => $isValid ? null : $storesPair,
        ];
    }

    /**
     * ترتيب المتاجر حسب المسافة (الأقرب فالأبعد) من نقطة البداية
     * 
     * @param array $storeIds قائمة معرفات المتاجر
     * @param float $startLat خط عرض نقطة البداية (موقع السائق)
     * @param float $startLng خط طول نقطة البداية
     * @param float $endLat خط عرض نقطة النهاية (موقع التوصيل)
     * @param float $endLng خط طول نقطة النهاية
     * @return array قائمة المتاجر مرتبة مع المسافات
     */
    public function sortStoresByDistance(
        array $storeIds,
        float $startLat,
        float $startLng,
        float $endLat,
        float $endLng
    ): array {
        $stores = Store::whereIn('id', $storeIds)
            ->whereNotNull('v_location')
            ->whereNotNull('h_location')
            ->get();

        // حساب المسافة من نقطة البداية لكل متجر
        $storesWithDistance = $stores->map(function ($store) use ($startLat, $startLng) {
            $distance = $this->calculateDistance(
                $startLat,
                $startLng,
                (float) $store->v_location,
                (float) $store->h_location
            );

            return [
                'id' => $store->id,
                'name' => $store->store_name,
                'lat' => (float) $store->v_location,
                'lng' => (float) $store->h_location,
                'distance_from_start' => round($distance, 2),
            ];
        });

        // ترتيب حسب المسافة من نقطة البداية (الأقرب أولاً)
        $sortedStores = $storesWithDistance->sortBy('distance_from_start')->values();

        // حساب المسافة من آخر متجر إلى موقع التوصيل
        $lastStore = $sortedStores->last();
        $distanceToDelivery = 0;
        
        if ($lastStore) {
            $distanceToDelivery = $this->calculateDistance(
                $lastStore['lat'],
                $lastStore['lng'],
                $endLat,
                $endLng
            );
        }

        // حساب المسافة الإجمالية
        $totalDistance = $sortedStores->sum('distance_from_start') + $distanceToDelivery;

        return [
            'stores_order' => $sortedStores->toArray(),
            'distance_to_delivery' => round($distanceToDelivery, 2),
            'total_estimated_distance' => round($totalDistance, 2),
        ];
    }

    /**
     * الحصول على ترتيب التنفيذ للطلب العادي
     * 
     * @param Order $order الطلب
     * @param Driver $driver السائق
     * @return array
     */
    public function getOrderExecutionRoute(Order $order, Driver $driver): array
    {
        // جمع معرفات المتاجر
        $storeIds = $order->items()
            ->with('store')
            ->get()
            ->pluck('store.id')
            ->unique()
            ->filter()
            ->toArray();

        if (empty($storeIds) || !$driver->current_lat || !$driver->current_lng) {
            return [
                'stores_order' => [],
                'distance_to_delivery' => 0,
                'total_estimated_distance' => 0,
            ];
        }

        return $this->sortStoresByDistance(
            $storeIds,
            (float) $driver->current_lat,
            (float) $driver->current_lng,
            (float) $order->delivery_lat,
            (float) $order->delivery_lng
        );
    }

    /**
     * الحصول على ترتيب التنفيذ للطلب الخاص
     * 
     * @param CustomOrder $order الطلب الخاص
     * @param Driver $driver السائق
     * @return array
     */
    public function getCustomOrderExecutionRoute(CustomOrder $order, Driver $driver): array
    {
        // جمع نقاط الاستلام
        $pickupPoints = $order->items()
            ->whereNotNull('pickup_lat')
            ->whereNotNull('pickup_lng')
            ->get()
            ->map(fn($item) => [
                'id' => $item->id,
                'description' => $item->description,
                'lat' => (float) $item->pickup_lat,
                'lng' => (float) $item->pickup_lng,
                'address' => $item->pickup_address,
            ]);

        if ($pickupPoints->isEmpty() || !$driver->current_lat || !$driver->current_lng) {
            return [
                'pickup_order' => [],
                'distance_to_delivery' => 0,
                'total_estimated_distance' => 0,
            ];
        }

        // حساب المسافة من موقع السائق لكل نقطة استلام
        $pickupsWithDistance = $pickupPoints->map(function ($point) use ($driver) {
            $distance = $this->calculateDistance(
                (float) $driver->current_lat,
                (float) $driver->current_lng,
                $point['lat'],
                $point['lng']
            );

            return array_merge($point, ['distance_from_start' => round($distance, 2)]);
        });

        // ترتيب حسب المسافة (الأقرب أولاً)
        $sortedPickups = $pickupsWithDistance->sortBy('distance_from_start')->values();

        // حساب المسافة من آخر نقطة إلى موقع التوصيل
        $lastPickup = $sortedPickups->last();
        $distanceToDelivery = 0;
        
        if ($lastPickup && $order->delivery_lat && $order->delivery_lng) {
            $distanceToDelivery = $this->calculateDistance(
                $lastPickup['lat'],
                $lastPickup['lng'],
                (float) $order->delivery_lat,
                (float) $order->delivery_lng
            );
        }

        // حساب المسافة الإجمالية
        $totalDistance = $sortedPickups->sum('distance_from_start') + $distanceToDelivery;

        return [
            'pickup_order' => $sortedPickups->toArray(),
            'distance_to_delivery' => round($distanceToDelivery, 2),
            'total_estimated_distance' => round($totalDistance, 2),
        ];
    }

    /**
     * جلب الطلبات العادية المتاحة ضمن نطاق السائق الجغرافي
     * 
     * @param Driver $driver السائق
     * @return Collection<Order>
     */
    public function getAvailableOrdersForDriver(Driver $driver): Collection
    {
        if (!$driver->current_lat || !$driver->current_lng) {
            return collect();
        }

        // جلب الطلبات المعلقة بدون سائق
        $pendingOrders = Order::where('status', Order::STATUS_PENDING)
            ->whereNull('driver_id')
            // ->where('confirmation_expires_at', '>', now())
            ->with(['items.product', 'items.store', 'user'])
            ->get();

        // فلترة حسب النطاق الجغرافي
        return $pendingOrders->filter(function (Order $order) use ($driver) {
            $centroid = $this->calculateOrderCentroid($order);
            $radius = $this->getCurrentRadius($order->created_at);
            
            return $this->isDriverInRadius($driver, $centroid['lat'], $centroid['lng'], $radius);
        });
    }

    /**
     * جلب الطلبات الخاصة المتاحة ضمن نطاق السائق الجغرافي
     * 
     * @param Driver $driver السائق
     * @return Collection<CustomOrder>
     */
    public function getAvailableCustomOrdersForDriver(Driver $driver): Collection
    {
        if (!$driver->current_lat || !$driver->current_lng) {
            return collect();
        }

        // جلب الطلبات المعلقة بدون سائق
        $pendingOrders = CustomOrder::where('status', CustomOrder::STATUS_PENDING)
            ->whereNull('driver_id')
            // ->where('confirmation_expires_at', '>', now())
            ->with(['items', 'user'])
            ->get();

        // فلترة حسب النطاق الجغرافي
        return $pendingOrders->filter(function (CustomOrder $order) use ($driver) {
            $centroid = $this->calculateCustomOrderCentroid($order);
            $radius = $this->getCurrentRadius($order->created_at);
            
            return $this->isDriverInRadius($driver, $centroid['lat'], $centroid['lng'], $radius);
        });
    }

    /**
     * معلومات النطاق الجغرافي الحالي للطلب
     * 
     * @param Carbon $orderCreatedAt وقت إنشاء الطلب
     * @return array
     */
    public function getGeofencingStatus(Carbon $orderCreatedAt): array
    {
        $minutesElapsed = now()->diffInMinutes($orderCreatedAt);
        $currentRadius = $this->getCurrentRadius($orderCreatedAt);
        $maxRadius = max(self::PROGRESSIVE_RADIUS_CONFIG);
        
        // الوقت المتبقي للتوسيع التالي
        $nextExpansion = null;
        foreach (self::PROGRESSIVE_RADIUS_CONFIG as $maxMinutes => $radius) {
            if ($minutesElapsed < $maxMinutes && $radius > $currentRadius) {
                $nextExpansion = [
                    'at_minutes' => $maxMinutes,
                    'new_radius_km' => $radius,
                    'seconds_remaining' => ($maxMinutes * 60) - ($minutesElapsed * 60),
                ];
                break;
            }
        }

        return [
            'minutes_elapsed' => $minutesElapsed,
            'current_radius_km' => $currentRadius,
            'max_radius_km' => $maxRadius,
            'is_at_max_radius' => $currentRadius >= $maxRadius,
            'next_expansion' => $nextExpansion,
            'config' => self::PROGRESSIVE_RADIUS_CONFIG,
        ];
    }
}
