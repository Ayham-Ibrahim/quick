# Quick API - AI Coding Agent Instructions

## Project Overview
Laravel 12 multi-vendor e-commerce API with delivery system. Three user types: Users, Store owners (via Provider), and Drivers. OTP-based authentication with Sanctum tokens.

## Critical Patterns

### Multi-Guard Authentication
4 Sanctum guards in [config/auth.php](config/auth.php): `api` (Users), `provider` (Provider), `store` (Store), `driver` (Driver).
```php
Auth::guard('provider')->user()  // Provider/Store owner operations
Auth::guard('store')->user()     // Store-level operations
Auth::guard('driver')->user()    // Driver operations
Auth::user()                     // Regular users (defaults to 'api')
```
**Route protection**: Most routes use `auth:sanctum` middleware. Guard determined by token type.

### Service Layer (STRICT)
Business logic in `app/Services/` - **NEVER in controllers**. Controllers inject services via constructor.
```php
// Services extend Service base class for error handling:
$this->throwExceptionJson('رسالة الخطأ', 400, $errors);
```
**CRITICAL: Use `DB::transaction()` for:**
- Wallet/financial operations (see [WalletService.php](app/Services/WalletService.php))
- Checkout/order creation (see [CheckoutService.php](app/Services/Checkout/CheckoutService.php))
- Multi-model updates (cart items, stock decrements)

### API Responses (Arabic messages REQUIRED)
Controllers extend [Controller.php](app/Http/Controllers/Controller.php):
```php
$this->success($data, 'رسالة النجاح', 200);
$this->error('رسالة الخطأ', 400);
$this->paginate($paginatedData, 'رسالة');
$this->paginateWithData($paginated, $additionalData, 'رسالة');  // For extra metadata
```
All error/success messages must be in Arabic.

### File Uploads (SECURITY CRITICAL)
**ALWAYS use** `FileStorage::storeFile()` - never Laravel's `store()`:
```php
$url = FileStorage::storeFile($request->file('image'), 'products', 'img');
// Suffixes: 'img', 'vid', 'aud', 'docs'
// Validates MIME types, extensions, and prevents double-extension attacks
```

### Form Validation
Extend [BaseFormRequest.php](app/Http/Requests/BaseFormRequest.php), NOT Laravel's FormRequest. Returns standardized JSON errors (422).

### Data Models & Architecture

**Product Structure** (3-level):
```
Product (name, description, is_accepted)
  └→ ProductVariant (sku, price, stock_quantity, is_active)
      └→ ProductVariantAttribute (attribute_id, attribute_value_id)
```
- Products require admin approval: `is_accepted` field
- Use scopes: `Product::accepted()`, `Product::pending()`
- Variants handle pricing & stock; product-level `quantity` is fallback

**Wallet System** (polymorphic):
```php
Wallet::morphTo('owner')  // Can belong to User or Driver
// Auto-created via DriverObserver on Driver creation
```
- 8-digit unique `wallet_code` via [WalletHelper](app/Helpers/WalletHelper.php)
- Providers can add balance to Driver wallets
- Transactions track Provider→Driver transfers

**Cart & Checkout Flow**:
1. `Cart::active()` scope gets user's current cart
2. CartItems reference Product + optional ProductVariant
3. Checkout: validates stock → applies coupon → creates Order → decrements stock → marks cart completed
4. Order has 7 statuses: pending, confirmed, processing, ready, shipped, delivered, cancelled

**Model Scopes** (use consistently):
- `Product::accepted()`, `pending()`
- `Cart::active()`
- `Order::pending()`, `byStatus($status)`
- `Coupon::active()`

**Polymorphic Relations**:
- `Rating::morphTo('rateable')` - Products/Stores/Drivers
- `Wallet::morphTo('owner')` - Users/Drivers

### Observers
[DriverObserver](app/Observers/DriverObserver.php) auto-creates Wallet on Driver creation. Register in [AppServiceProvider](app/Providers/AppServiceProvider.php).

## Commands
```bash
composer run dev      # Runs: server + queue + logs + vite (queue needed for OTP sending)
composer run test     # PHPUnit with in-memory SQLite (see phpunit.xml)
composer run setup    # Fresh install: deps + .env + key + migrate + npm
vendor/bin/pint       # Code formatting (Laravel Pint)
```

## Key Routes
- **Auth**: `POST /register` → `POST /confirm-registration` (OTP flow returns Sanctum token)
  - Also: `/login` → `/confirm-login`, `/forgot-password` → `/confirm-forgot-password` → `/reset-password`
  - `/resend-otp` for OTP retry
- **Products**: 
  - `GET /products` (accepted only), `POST /products` (store owner)
  - `GET /pending-products`, `POST /accept-product/{id}` (admin approval flow)
  - `GET /my-products` (store owner's products)
- **Cart**: `GET /cart`, `POST /cart/items`, `PUT /cart/items/{id}`, `GET /cart/validate`
- **Checkout**: `GET /checkout/preview`, `POST /checkout/validate-coupon`, `POST /checkout`
- **Wallet**: `GET /my-wallet`, `POST /wallet/add-balance`
- **Profile**: `GET /user/profile`, `GET /store/profile`, `GET /driver/profile`

## Gotchas
- **Guards**: Always specify correct guard for Store/Driver operations
- **Arabic**: All user-facing messages in Arabic (errors, success, validation)
- **Transactions**: Wrap wallet ops, checkout, multi-model updates in `DB::transaction()`
- **Product approval**: Products start with `is_accepted = false`, need admin approval
- **Variant stock**: Check `ProductVariant->stock_quantity` if variant exists, else `Product->quantity`
- **Queue**: OTP sending uses queues - must run `php artisan queue:listen` (included in `composer run dev`)
- **Security**: Never use Laravel's native `store()` for files - use `FileStorage::storeFile()`
- **Testing**: Uses in-memory SQLite (see [phpunit.xml](phpunit.xml)), no need for test DB setup
