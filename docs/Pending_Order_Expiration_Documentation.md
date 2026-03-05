# Pending Order Expiration System - Architecture Documentation

## نظرة عامة

نظام Event-Driven لإدارة انتهاء صلاحية الطلبات المعلقة باستخدام Delayed Jobs.

## الجدول الزمني

```
    0 min          30 min               60 min
      │               │                    │
      ▼               ▼                    ▼
  [إنشاء الطلب]    [تذكير]            [انتهاء الصلاحية]
      │               │                    │
      │               │                    ▼
      │               │            إذا لا يزال pending:
      │               │            - status → cancelled
      │               ▼            - إشعار المستخدم
      │          إذا لا يزال pending:
      │          - إعادة إرسال للسائقين
      ▼
   dispatch Jobs
```

## المكونات

### 1. Jobs

#### RemindDriversAboutPendingOrder
- **الملف**: `app/Jobs/RemindDriversAboutPendingOrder.php`
- **الوظيفة**: إعادة إرسال إشعارات للسائقين بعد 30 دقيقة
- **Queue**: `order-expiration`

#### ExpirePendingOrder
- **الملف**: `app/Jobs/ExpirePendingOrder.php`
- **الوظيفة**: إلغاء الطلب وإشعار المستخدم بعد 60 دقيقة
- **Queue**: `order-expiration`

### 2. Service

#### PendingOrderExpirationService
- **الملف**: `app/Services/PendingOrderExpirationService.php`
- **الوظيفة**: جدولة وإعادة جدولة Jobs

```php
// عند إنشاء الطلب:
$this->pendingOrderExpirationService->scheduleExpirationJobs($order);

// عند إعادة المحاولة:
$this->pendingOrderExpirationService->rescheduleExpirationJobs($order);
```

## Fail-Safe Checks

كل Job يتضمن فحوصات أمان:

1. **Order Exists**: التحقق من وجود الطلب
2. **Still Pending**: التحقق من أن الحالة = pending و driver_id = null
3. **Expiration Reference**: مطابقة `confirmation_expires_at` لمنع تنفيذ Jobs القديمة

## Race Conditions & Multiple Workers

### منع التنفيذ المتوازي
```php
public function middleware(): array
{
    return [
        (new WithoutOverlapping("{$this->orderType}-{$this->orderId}-expire"))
            ->dontRelease()
            ->expireAfter(300),
    ];
}
```

### Double-Check في Transaction
```php
DB::transaction(function () use ($order) {
    $freshOrder = $this->getOrder();
    if (!$freshOrder || !$this->isOrderStillPending($freshOrder)) {
        return; // تم قبول الطلب أثناء التنفيذ
    }
    // إلغاء الطلب
});
```

## Retry Strategy

```php
public int $tries = 3;
public array $backoff = [30, 60, 120]; // Progressive backoff
public int $timeout = 60;
```

## لماذا Event-Driven أفضل من Scheduler Polling؟

### Scheduler Polling (الطريقة التقليدية)
```php
// في app/Console/Kernel.php:
$schedule->command('orders:check-pending')->everyMinute();
```

**المشاكل:**
- ❌ استعلام على كل الطلبات كل دقيقة
- ❌ مع 10,000 طلب/يوم = 14,400,000 صف يُفحص يومياً!
- ❌ ضغط كبير على قاعدة البيانات
- ❌ تأخير حتى 59 ثانية في أسوأ الحالات
- ❌ لا يتوسع مع زيادة الطلبات

### Event-Driven (الحل الحالي)
**المميزات:**
- ✅ Job واحد لكل طلب ← O(1) بدلاً من O(n)
- ✅ لا يوجد فحص دوري ← صفر ضغط على DB
- ✅ توقيت دقيق (30/60 دقيقة بالضبط)
- ✅ يتوسع أفقياً مع Queue Workers

## Redis vs Database Queue

### Redis (موصى به للإنتاج)
```
✓ أسرع (in-memory)
✓ يدعم delayed jobs بكفاءة عالية
✓ atomic operations
✓ يدعم millions of jobs
```

### Database
```
✓ لا يحتاج infrastructure إضافية
✓ جيد للتطوير
⚠ أبطأ مع كثرة الـ jobs
⚠ يحتاج index على delayed_until column
```

## تشغيل Queue Worker

### أثناء التطوير
```bash
php artisan queue:work --queue=order-expiration --tries=3 --backoff=30,60,120
```

### في الإنتاج (Supervisor)
```ini
[program:order-expiration-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/artisan queue:work --queue=order-expiration --tries=3 --backoff=30,60,120
autostart=true
autorestart=true
numprocs=2
user=www-data
redirect_stderr=true
stdout_logfile=/var/log/order-expiration-worker.log
```

## السيناريوهات

### 1. الطلب يُقبل قبل 30 دقيقة
```
T+0:   Order created, Jobs scheduled
T+10:  Driver accepts order
T+30:  Reminder Job runs → status != pending → SKIP ✓
T+60:  Expiration Job runs → status != pending → SKIP ✓
```

### 2. الطلب يُلغى ثم يُعاد
```
T+0:   Order created, Jobs scheduled (ref=A)
T+15:  Order cancelled by admin
T+20:  User retries → Jobs rescheduled (ref=B)
T+30:  Old Reminder Job (ref=A) runs → ref mismatch → SKIP ✓
T+50:  New Reminder Job (ref=B) runs → sends notifications ✓
T+60:  Old Expiration Job (ref=A) runs → ref mismatch → SKIP ✓
T+80:  New Expiration Job (ref=B) runs → expires order ✓
```

### 3. لا يوجد سائق متاح
```
T+0:   Order created, Jobs scheduled
T+30:  Reminder Job runs → resends to drivers
T+60:  Expiration Job runs → cancels order, notifies user
```

## Logging

جميع الإجراءات مسجلة في `storage/logs/laravel.log`:

```
[INFO] PendingOrderExpirationService: Jobs scheduled {order_id: 123}
[INFO] RemindDriversAboutPendingOrder: Starting {order_id: 123}
[INFO] RemindDriversAboutPendingOrder: Sent reminder notifications {sent_count: 5}
[INFO] ExpirePendingOrder: Order expired successfully {order_id: 123}
```

## التكامل في الكود

### CheckoutService
```php
public function checkout(array $data): Order
{
    // ... إنشاء الطلب ...

    // جدولة Jobs الانتهاء
    $this->pendingOrderExpirationService->scheduleExpirationJobs($order);

    return $order;
}
```

### CustomOrderService
```php
public function createOrder(array $data)
{
    // ... إنشاء الطلب ...

    // جدولة Jobs الانتهاء
    $this->pendingOrderExpirationService->scheduleExpirationJobs($order);

    return $order;
}
```

### retryDelivery (Order & CustomOrder)
```php
public function retryDelivery(int $orderId)
{
    // ... إعادة الطلب للحالة المعلقة ...

    // إعادة جدولة Jobs (القديمة ستتجاهل نفسها)
    $this->pendingOrderExpirationService->rescheduleExpirationJobs($order);

    return $order;
}
```

## الإعدادات القابلة للتخصيص

```php
// في PendingOrderExpirationService
const REMINDER_DELAY_MINUTES = 30;     // وقت التذكير
const EXPIRATION_DELAY_MINUTES = 60;   // وقت الانتهاء
const QUEUE_NAME = 'order-expiration'; // اسم الـ Queue
```
