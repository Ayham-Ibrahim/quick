<?php

namespace App\Services\Checkout;

use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\CartItem;
use App\Models\ProductVariant;
use App\Models\DiscountManagement\Coupon;
use App\Models\DiscountManagement\CouponUsage;
use App\Services\Service;
use App\Services\NotificationService;
use App\Services\Geofencing\GeofencingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Exceptions\HttpResponseException;

class CheckoutService extends Service
{
    protected GeofencingService $geofencingService;
    protected NotificationService $notificationService;

    public function __construct(GeofencingService $geofencingService, NotificationService $notificationService)
    {
        $this->geofencingService = $geofencingService;
        $this->notificationService = $notificationService;
    }

    /**
     * إجراء عملية الدفع وإنشاء الطلب
     */
    public function checkout(array $data): Order
    {
        try {
            return DB::transaction(function () use ($data) {
                $user = Auth::user();
                $cart = $this->getActiveCart();

                // التحقق من أن السلة ليست فارغة
                if ($cart->isEmpty()) {
                    $this->throwExceptionJson('السلة فارغة', 400);
                }

                // التحقق من توفر المنتجات والكميات
                $this->validateCartItems($cart);

                // التحقق من المسافة بين المتاجر
                $this->validateStoresDistance($cart);

                // جلب وتحقق من الكوبون (إن وجد)
                $couponData = null;
                if (!empty($data['coupon_code'])) {
                    $couponData = $this->validateAndCalculateCoupon(
                        $data['coupon_code'],
                        $cart,
                        $user->id
                    );
                }

                // حساب المبالغ
                $totals = $this->calculateTotals($cart, $couponData);

                // رسوم التوصيل (يمكن إرسالها من الـ request أو حسابها مستقبلاً)
                $deliveryFee = $data['delivery_fee'] ?? 0;

                // إنشاء الطلب مع فترة انتظار السائق
                $order = Order::create([
                    'user_id' => $user->id,
                    //TODO: remove the driver id
                    'driver_id' => 15,
                    'coupon_id' => $couponData['coupon']->id ?? null,
                    'coupon_code' => $couponData['coupon']->code ?? null,
                    'subtotal' => $totals['subtotal'],
                    'discount_amount' => $totals['discount'],
                    'delivery_fee' => $deliveryFee,
                    'total' => $totals['total'] + $deliveryFee,
                    'status' => Order::STATUS_PENDING,
                    'confirmation_expires_at' => now()->addMinutes(Order::DRIVER_CONFIRMATION_TIMEOUT_MINUTES),
                    'delivery_address' => $data['delivery_address'],
                    'delivery_lat' => $data['delivery_lat'],
                    'delivery_lng' => $data['delivery_lng'],
                    'requested_delivery_at' => $data['requested_delivery_at'] ?? null,
                    'is_immediate_delivery' => $data['is_immediate_delivery'] ?? true,
                    'notes' => $data['notes'] ?? null,
                ]);

                // إنشاء عناصر الطلب مع توزيع الخصم
                $this->createOrderItems($order, $cart, $couponData);

                // تسجيل استخدام الكوبون
                if ($couponData) {
                    $this->recordCouponUsage($couponData['coupon'], $user->id, $order->id);
                }

                // خصم الكميات من المخزون
                $this->decrementStock($cart);

                // تحديث حالة السلة
                $cart->markAsCompleted();

                $order = $order->load(['items.product', 'items.store', 'coupon', 'user']);

                // Notify user that order was created
                $this->notificationService->notifyUserOrderCreated($order);

                // Notify stores about new order
                $this->notificationService->notifyStoresNewOrder($order);

                return $order;
            });
        } catch (\Throwable $th) {
            Log::error('Checkout error: ' . $th->getMessage(), [
                'trace' => $th->getTraceAsString()
            ]);

            if ($th instanceof HttpResponseException) {
                throw $th;
            }

            $this->throwExceptionJson('حدث خطأ أثناء إتمام الطلب', 500);
            throw $th; // للتأكد من أن الـ analyzer يعرف أن هذا path لا يعود
        }
    }

    /**
     * معاينة الطلب قبل التأكيد (مع أو بدون كوبون)
     */
    public function preview(?string $couponCode = null): array
    {
        $user = Auth::user();
        $cart = $this->getActiveCart();

        if ($cart->isEmpty()) {
            $this->throwExceptionJson('السلة فارغة', 400);
        }

        // التحقق من الكوبون
        $couponData = null;
        if ($couponCode) {
            $couponData = $this->validateAndCalculateCoupon($couponCode, $cart, $user->id);
        }

        $totals = $this->calculateTotals($cart, $couponData);

        return [
            'cart_id' => $cart->id,
            'items_count' => $cart->items->count(),
            'total_quantity' => $cart->total_items,
            'subtotal' => $totals['subtotal'],
            'discount' => [
                'amount' => $totals['discount'],
                'coupon_code' => $couponData['coupon']->code ?? null,
                'coupon_type' => $couponData['coupon']->type ?? null,
                'coupon_value' => $couponData['coupon']->amount ?? null,
                'applicable_items_count' => $couponData['applicable_items_count'] ?? 0,
            ],
            'total' => $totals['total'],
            'items_with_discount' => $couponData['items_breakdown'] ?? [],
        ];
    }

    /**
     * التحقق من صلاحية الكوبون (بدون تطبيق)
     */
    public function validateCoupon(string $couponCode): array
    {
        $user = Auth::user();
        $cart = $this->getActiveCart();

        if ($cart->isEmpty()) {
            $this->throwExceptionJson('السلة فارغة', 400);
        }

        $couponData = $this->validateAndCalculateCoupon($couponCode, $cart, $user->id);

        return [
            'is_valid' => true,
            'coupon' => [
                'code' => $couponData['coupon']->code,
                'type' => $couponData['coupon']->type,
                'amount' => $couponData['coupon']->amount,
            ],
            'discount_amount' => $couponData['discount'],
            'applicable_items_count' => $couponData['applicable_items_count'],
            'message' => 'الكوبون صالح للاستخدام',
        ];
    }

    /* ================= Private Methods ================= */

    /**
     * جلب السلة النشطة للمستخدم
     */
    private function getActiveCart(): Cart
    {
        $cart = Cart::where('user_id', Auth::id())
            ->active()
            ->with([
                'items.product.store',
                'items.product.coupons',
                'items.variant',
            ])
            ->first();

        if (!$cart) {
            $this->throwExceptionJson('لا توجد سلة نشطة', 404);
        }

        return $cart;
    }

    /**
     * التحقق من توفر المنتجات والكميات
     */
    private function validateCartItems(Cart $cart): void
    {
        foreach ($cart->items as $item) {
            // التحقق من وجود المنتج
            if (!$item->product || !$item->product->is_accepted) {
                $this->throwExceptionJson(
                    "المنتج '{$item->product?->name}' غير متاح حالياً",
                    400
                );
            }

            // التحقق من توفر الكمية
            if (!$item->isQuantityAvailable()) {
                $available = $item->available_stock;
                $this->throwExceptionJson(
                    "الكمية المطلوبة من '{$item->product->name}' غير متوفرة. المتاح: {$available}",
                    400
                );
            }

            // التحقق من المتغير إن وجد
            if ($item->product_variant_id) {
                if (!$item->variant || !$item->variant->is_active) {
                    $this->throwExceptionJson(
                        "المتغير المحدد للمنتج '{$item->product->name}' غير متاح",
                        400
                    );
                }
            }
        }
    }

    /**
     * التحقق من الكوبون وحساب الخصم
     */
    private function validateAndCalculateCoupon(string $couponCode, Cart $cart, int $userId): array
    {
        // جلب الكوبون مع المنتجات المرتبطة
        $coupon = Coupon::where('code', $couponCode)
            ->with('products')
            ->first();

        if (!$coupon) {
            $this->throwExceptionJson('الكوبون غير موجود', 404);
        }

        // التحقق من أن الكوبون فعال
        if (!$coupon->is_active) {
            if ($coupon->isExpired()) {
                $this->throwExceptionJson('انتهت صلاحية الكوبون', 400);
            }
            $this->throwExceptionJson('الكوبون غير فعال حالياً', 400);
        }

        // التحقق من حد الاستخدام للمستخدم
        if (!$coupon->canBeUsedBy($userId)) {
            $this->throwExceptionJson('لقد تجاوزت الحد المسموح لاستخدام هذا الكوبون', 400);
        }

        // جلب معرفات المنتجات المرتبطة بالكوبون
        $couponProductIds = $coupon->products->pluck('id')->toArray();

        if (empty($couponProductIds)) {
            $this->throwExceptionJson('الكوبون غير مرتبط بأي منتجات', 400);
        }

        // فلترة عناصر السلة المرتبطة بالكوبون
        $applicableItems = $cart->items->filter(function ($item) use ($couponProductIds) {
            return in_array($item->product_id, $couponProductIds);
        });

        if ($applicableItems->isEmpty()) {
            $this->throwExceptionJson('لا توجد منتجات في السلة يمكن تطبيق الكوبون عليها', 400);
        }

        // حساب الخصم لكل عنصر
        $totalDiscount = 0;
        $itemsBreakdown = [];

        foreach ($applicableItems as $item) {
            // السعر من variant أو product
            $unitPrice = $item->variant
                ? (float) $item->variant->price
                : (float) $item->product->current_price;

            $itemSubtotal = $unitPrice * $item->quantity;

            // حساب الخصم حسب نوع الكوبون
            $itemDiscount = $coupon->type === 'percentage'
                ? ($itemSubtotal * $coupon->amount / 100)
                : min($coupon->amount, $itemSubtotal); // fixed: لا يتجاوز سعر المنتج

            $totalDiscount += $itemDiscount;

            $itemsBreakdown[] = [
                'cart_item_id' => $item->id,
                'product_id' => $item->product_id,
                'product_name' => $item->product->name,
                'variant_id' => $item->product_variant_id,
                'quantity' => $item->quantity,
                'unit_price' => $unitPrice,
                'subtotal' => $itemSubtotal,
                'discount' => round($itemDiscount, 2),
                'total_after_discount' => round($itemSubtotal - $itemDiscount, 2),
            ];
        }

        return [
            'coupon' => $coupon,
            'discount' => round($totalDiscount, 2),
            'applicable_items_count' => $applicableItems->count(),
            'items_breakdown' => $itemsBreakdown,
        ];
    }

    /**
     * حساب الإجماليات
     */
    private function calculateTotals(Cart $cart, ?array $couponData): array
    {
        $subtotal = 0;

        foreach ($cart->items as $item) {
            // السعر من variant أو product
            $unitPrice = $item->variant
                ? (float) $item->variant->price
                : (float) $item->product->current_price;

            $subtotal += $unitPrice * $item->quantity;
        }

        $discount = $couponData['discount'] ?? 0;
        $total = max(0, $subtotal - $discount);

        return [
            'subtotal' => round($subtotal, 2),
            'discount' => round($discount, 2),
            'total' => round($total, 2),
        ];
    }

    /**
     * إنشاء عناصر الطلب
     */
    private function createOrderItems(Order $order, Cart $cart, ?array $couponData): void
    {
        // تحويل breakdown إلى map للوصول السريع
        $discountMap = [];
        if ($couponData) {
            foreach ($couponData['items_breakdown'] as $breakdown) {
                $key = $breakdown['cart_item_id'];
                $discountMap[$key] = $breakdown['discount'];
            }
        }

        foreach ($cart->items as $item) {
            // السعر من variant أو product
            $unitPrice = $item->variant
                ? (float) $item->variant->price
                : (float) $item->product->current_price;

            $itemSubtotal = $unitPrice * $item->quantity;
            $itemDiscount = $discountMap[$item->id] ?? 0;
            $lineTotal = $itemSubtotal - $itemDiscount;

            // بناء تفاصيل المتغير
            $variantDetails = OrderItem::buildVariantDetails($item->variant);

            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $item->product_id,
                'product_variant_id' => $item->product_variant_id,
                'store_id' => $item->product->store_id,
                'quantity' => $item->quantity,
                'unit_price' => $unitPrice,
                'discount_amount' => $itemDiscount,
                'line_total' => round($lineTotal, 2),
                'product_name' => $item->product->name,
                'variant_details' => $variantDetails,
            ]);
        }
    }

    /**
     * تسجيل استخدام الكوبون
     */
    private function recordCouponUsage(Coupon $coupon, int $userId, int $orderId): void
    {
        CouponUsage::create([
            'coupon_id' => $coupon->id,
            'user_id' => $userId,
            'order_id' => $orderId,
        ]);
    }

    /**
     * خصم الكميات من المخزون
     */
    private function decrementStock(Cart $cart): void
    {
        foreach ($cart->items as $item) {
            if ($item->variant) {
                // خصم من variant
                $item->variant->decrement('stock_quantity', $item->quantity);
            } else {
                // خصم من product
                if ($item->product->quantity !== null) {
                    $item->product->decrement('quantity', $item->quantity);
                }
            }
        }
    }

    /**
     * التحقق من المسافة بين المتاجر في السلة
     * 
     * لا يُسمح بالطلب إذا كانت المسافة بين أي متجرين > 3 كم
     */
    private function validateStoresDistance(Cart $cart): void
    {
        // جمع معرفات المتاجر الفريدة
        $storeIds = $cart->items
            ->pluck('product.store_id')
            ->unique()
            ->filter()
            ->toArray();

        // إذا كان متجر واحد فقط، لا حاجة للتحقق
        if (count($storeIds) <= 1) {
            return;
        }

        $result = $this->geofencingService->validateStoresDistance($storeIds);

        if (!$result['valid']) {
            $store1Name = $result['stores_pair']['store1']['name'] ?? 'متجر 1';
            $store2Name = $result['stores_pair']['store2']['name'] ?? 'متجر 2';
            
            $this->throwExceptionJson(
                "لا يمكن إتمام الطلب: المسافة بين \"{$store1Name}\" و \"{$store2Name}\" تتجاوز الحد المسموح ({$result['max_allowed']} كم). المسافة الفعلية: {$result['max_distance']} كم",
                400,
                [
                    'error_type' => 'stores_distance_exceeded',
                    'max_distance' => $result['max_distance'],
                    'max_allowed' => $result['max_allowed'],
                    'stores_pair' => $result['stores_pair'],
                ]
            );
        }
    }
}
