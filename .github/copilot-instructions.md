# Quick API - AI Coding Agent Instructions

## Project Overview
Laravel 12 multi-vendor e-commerce API with delivery system. Three user types: Users (customers), Providers (store owners), and Drivers. OTP-based authentication with Sanctum tokens.

## Developer Commands
```bash
composer run dev     # Starts server, queue, logs (pail), vite concurrently
composer run test    # Clears config, runs PHPUnit with in-memory SQLite
composer run setup   # Full install: dependencies, env, migrate, npm build
```

## Multi-Guard Authentication
4 Sanctum guards in [config/auth.php](config/auth.php):
| Guard | Provider Model | Use Case |
|-------|---------------|----------|
| `api` | `User` | Customer operations (default) |
| `provider` | `Provider` | Store owner management |
| `store` | `Store` | Store-level product operations |
| `driver` | `Driver` | Delivery operations |

```php
Auth::guard('store')->user()   // Store-level operations (products, inventory)
Auth::guard('driver')->user()  // Driver operations
Auth::user()                   // Regular users (api guard is default)
```

## Architecture Patterns

### Service Layer (MANDATORY)
All business logic in `app/Services/`. Controllers ONLY call services + return responses.
```php
// Controller pattern:
public function __construct(ProductService $productService) { ... }
public function store(StoreProductRequest $request) {
    $product = $this->productService->storeProduct($request->validated());
    return $this->success(new ProductResource($product), 'تم إنشاء المنتج');
}

// Services extend Service base class for standardized exceptions:
$this->throwExceptionJson('رسالة الخطأ', 400, $errors);
```

### DB Transactions (REQUIRED for multi-model ops)
Use `DB::transaction()` for: wallet operations, checkout, stock updates, product+variants creation.
See [CheckoutService.php](app/Services/Checkout/CheckoutService.php) and [ProductService.php](app/Services/Products/ProductService.php).

### API Response Methods
Controllers extend [Controller.php](app/Http/Controllers/Controller.php). **All messages in Arabic**:
```php
$this->success($data, 'تم بنجاح', 200);
$this->error('حدث خطأ', 400);
$this->paginate($paginator, 'تم جلب البيانات');
$this->paginateWithData($paginator, $extraData, 'رسالة');
```

### Form Requests
Extend [BaseFormRequest.php](app/Http/Requests/BaseFormRequest.php) — auto-returns JSON 422 errors.

### File Uploads (SECURITY)
**ALWAYS use** [FileStorage::storeFile()](app/Services/FileStorage.php) — validates MIME, extensions, blocks double-extensions:
```php
$url = FileStorage::storeFile($file, 'products', 'img'); // 'img'|'vid'|'aud'|'docs'
```

## Order Systems

### Two Order Types
| Type | Model | Description |
|------|-------|-------------|
| **Regular Order** | `Order` | Products from stores → Cart → Checkout |
| **Custom Order** | `CustomOrder` | "اطلب أي شيء" - User describes items + pickup locations |

### Simplified Status System (4 statuses only)
Both order types share the same 4 statuses:

| Status | Arabic | Description |
|--------|--------|-------------|
| `pending` | معلق | Order created, waiting for driver |
| `shipping` | قيد التوصيل | Driver accepted, delivering |
| `delivered` | تم التسليم | Successfully delivered ✅ |
| `cancelled` | ملغي | Cancelled/failed (with reason) |

### Order Flow (Both Types)
```
pending → shipping → delivered
   │          │
   │          │
   ▼          ▼
cancelled  cancelled (with reason)
```

1. User creates order → status: `pending` (5 min timeout for driver)
2. Driver accepts → status: `shipping`
3. Driver delivers → status: `delivered`
4. OR Driver cancels with reason → status: `cancelled`
5. User can retry cancelled orders → back to `pending`

### Cancellation Handling
```php
// Driver cancels delivery with reason:
$order->markAsCancelled('العميل غير متواجد');
$customOrder->markAsCancelled('العنوان غير صحيح');

// User retries delivery (sends back to drivers):
$order->retryDelivery();       // status → pending
$customOrder->retryDelivery(); // status → pending
```

### Delivery Fee Calculation
```php
$kmPrice = ProfitRatios::getValueByTag('km_price');
$deliveryFee = $distanceKm * $kmPrice;  // Frontend sends distance_km
```

### Driver Acceptance Pattern
Both order types use 5-minute timeout for driver acceptance:
```php
Order::DRIVER_CONFIRMATION_TIMEOUT_MINUTES = 5;
CustomOrder::DRIVER_CONFIRMATION_TIMEOUT_MINUTES = 5;

// Scopes for available orders
Order::availableForDrivers()      // pending + not expired
CustomOrder::availableForDrivers() // pending + not expired
```

## Data Model Patterns

### Product Structure (3-level hierarchy)
```
Product → ProductVariant → ProductVariantAttribute
                ↓                    ↓
           sku, price,        attribute_id,
           stock_quantity     attribute_value_id
```
- Products with variants: stock/price on `ProductVariant`
- Products without variants: stock on `Product->quantity`, price on `Product->current_price`
- New products: `is_accepted = false` (requires admin approval)

### Model Scopes
```php
Product::accepted()   // where is_accepted = true
Product::pending()    // where is_accepted = false
Cart::active()        // where status = 'active'
Coupon::is_active     // computed accessor: within dates, under usage limit
ProfitRatios::getValueByTag('km_price')  // get config value by tag
```

### Polymorphic Wallet
```php
Wallet::morphTo('owner')  // Owner can be User or Driver
// Auto-created for Driver via DriverObserver
```

## Key Configuration

### ProfitRatios (system config via DB)
Tags: `order_profit_percentage`, `delivery_profit_per_ride_bike`, `delivery_profit_per_ride_motorbike`, `km_price`, `minimum_order_value`

### Observers
- [DriverObserver](app/Observers/DriverObserver.php): Creates wallet on driver registration

### Geofencing (Progressive Radius)
See [GeofencingService](app/Services/Geofencing/GeofencingService.php) and [docs](docs/Geofencing_Documentation.md).
```php
// النطاق الجغرافي التدريجي (كم)
0-2 min → 1 km | 2-4 min → 2 km | 4-6 min → 3 km | 6-8 min → 4 km | 8-10 min → 5 km

// قيود المتاجر
MAX_DISTANCE_BETWEEN_STORES_KM = 3  // الحد الأقصى بين أي متجرين
DRIVER_ACTIVITY_TIMEOUT_MINUTES = 5 // مدة اعتبار السائق نشطاً
```

## Testing
Uses in-memory SQLite ([phpunit.xml](phpunit.xml)). No DB setup needed.
```bash
composer run test
```

## Quick Reference
| Pattern | Location | Note |
|---------|----------|------|
| API Resources | `app/Http/Resources/` | Transform models for JSON |
| Form Requests | `app/Http/Requests/{Domain}/` | Extend BaseFormRequest |
| Services | `app/Services/{Domain}/` | All business logic here |
| Observers | `app/Observers/` | Model event hooks |
| Helpers | `app/Helpers/` | Utility functions (WalletHelper) |
| Geofencing | `app/Services/Geofencing/` | Geographic driver matching |

## API Routes Summary

### Driver Location & Status
```
POST   /driver/location                  # تحديث الموقع (كل 30 ثانية)
POST   /driver/toggle-online             # تبديل حالة الاتصال
POST   /driver/heartbeat                 # تسجيل النشاط (كل دقيقة)
```

### Regular Orders
```
GET    /orders                           # قائمة طلباتي
GET    /orders/{id}                      # تفاصيل طلب
POST   /orders/{id}/cancel               # إلغاء الطلب
POST   /orders/{id}/retry-delivery       # إعادة محاولة التوصيل
POST   /orders/{id}/reorder              # إعادة طلب طلبية مكتملة (تم التوصيل)

# Driver endpoints
GET    /driver/orders/available          # الطلبات المتاحة
GET    /driver/orders/my                 # طلباتي
POST   /driver/orders/{id}/accept        # قبول طلب (مع ترتيب التنفيذ)
POST   /driver/orders/{id}/deliver       # تأكيد التسليم
POST   /driver/orders/{id}/cancel        # إلغاء (مع سبب)
```

### Custom Orders (اطلب أي شيء)
```
POST   /custom-orders                    # إنشاء طلب خاص
POST   /custom-orders/calculate-fee      # حساب سعر التوصيل
GET    /custom-orders                    # طلباتي الخاصة
GET    /custom-orders/{id}               # تفاصيل طلب
POST   /custom-orders/{id}/cancel        # إلغاء
POST   /custom-orders/{id}/retry-delivery # إعادة محاولة التوصيل

# Driver endpoints
GET    /driver/custom-orders/available   # الطلبات المتاحة
GET    /driver/custom-orders/my          # طلباتي
POST   /driver/custom-orders/{id}/accept # قبول طلب
POST   /driver/custom-orders/{id}/deliver # تأكيد التوصيل
POST   /driver/custom-orders/{id}/cancel  # إلغاء (مع سبب)
```
