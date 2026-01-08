# Quick API - AI Coding Agent Instructions

## Project Overview
Laravel 12 multi-vendor e-commerce API with delivery system. Three user types: Users, Store owners, and Drivers. OTP-based authentication with Sanctum tokens.

## Critical Patterns

### Multi-Guard Authentication
4 Sanctum guards in [config/auth.php](config/auth.php): `api` (Users), `provider` (Provider), `store` (Store), `driver` (Driver).
```php
Auth::guard('provider')->user()  // Store operations
Auth::guard('driver')->user()    // Driver operations
Auth::user()                     // Regular users (defaults to 'api')
```

### Service Layer (STRICT)
Business logic in `app/Services/` - **NEVER in controllers**. Controllers inject services via constructor.
```php
// Services extend Service base class for error handling:
$this->throwExceptionJson('رسالة الخطأ', 400, $errors);
```
**Use `DB::transaction()` for wallet/financial operations.**

### API Responses (Arabic messages required)
Controllers extend [Controller.php](app/Http/Controllers/Controller.php):
```php
$this->success($data, 'رسالة النجاح', 200);
$this->error('رسالة الخطأ', 400);
$this->paginate($paginatedData, 'رسالة');
```

### File Uploads (SECURITY CRITICAL)
**ALWAYS use** `FileStorage::storeFile()` - never Laravel's `store()`:
```php
$url = FileStorage::storeFile($request->file('image'), 'products', 'img');
// Suffixes: 'img', 'vid', 'aud', 'docs'
```

### Form Validation
Extend [BaseFormRequest.php](app/Http/Requests/BaseFormRequest.php), not Laravel's FormRequest. Arabic validation messages.

### Product Structure
Three-level: `Product` → `ProductVariant` (price/stock) → `ProductVariantAttribute` (size, color, etc.)

## Commands
```bash
composer run dev      # Runs server + queue + logs + vite (queue needed for OTP)
composer run test     # PHPUnit with in-memory SQLite
vendor/bin/pint       # Code formatting
```

## Key Routes
- **Auth**: `POST /register` → `POST /confirm-registration` (OTP flow returns Sanctum token)
- **Store**: `GET /store/profile`, `GET /store/{id}/products`
- **Products**: `GET /pending-products`, `POST /accept-product/{id}` (admin approval)
- **Cart**: `/cart/*` routes for shopping cart operations

## Gotchas
- Always specify guard for Store/Driver operations
- All user-facing messages in Arabic
- Wallet operations need `DB::transaction()`
- Products require admin approval (pending → approved)
