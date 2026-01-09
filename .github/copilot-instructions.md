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
      └→ ProductVariantAttribute (attribute_id, attribute_value_id)
```

**Wallet System** (polymorphic):
Wallet::morphTo('owner')  // Can belong to User or Driver
// Auto-created via DriverObserver on Driver creation
```
- Providers can add balance to Driver wallets
- Transactions track Provider→Driver transfers
1. `Cart::active()` scope gets user's current cart
2. CartItems reference Product + optional ProductVariant
4. Order has 7 statuses: pending, confirmed, processing, ready, shipped, delivered, cancelled

- `Product::accepted()`, `pending()`
- `Cart::active()`
- `Coupon::active()`


### Observers

## Commands

## Key Routes
- **Products**: 
  - `GET /products` (accepted only), `POST /products` (store owner)
- **Profile**: `GET /user/profile`, `GET /store/profile`, `GET /driver/profile`

- **Arabic**: All user-facing messages in Arabic (errors, success, validation)
- **Transactions**: Wrap wallet ops, checkout, multi-model updates in `DB::transaction()`
- **Product approval**: Products start with `is_accepted = false`, need admin approval
- **Variant stock**: Check `ProductVariant->stock_quantity` if variant exists, else `Product->quantity`
- **Queue**: OTP sending uses queues - must run `php artisan queue:listen` (included in `composer run dev`)
- **Security**: Never use Laravel's native `store()` for files - use `FileStorage::storeFile()`
- **Testing**: Uses in-memory SQLite (see [phpunit.xml](phpunit.xml)), no need for test DB setup
