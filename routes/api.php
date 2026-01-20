<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AdsController;
use App\Http\Controllers\AttributeController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\CustomOrderController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\StoreController;
use App\Http\Controllers\DriverController;
use App\Http\Controllers\RatingController;
use App\Http\Controllers\WalletController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\VehicleTypeController;
use App\Http\Controllers\Categories\CategoryController;
use App\Http\Controllers\Categories\SubCategoryController;
use App\Http\Controllers\ComplaintController;
use App\Http\Controllers\DiscountManagement\CouponController;
use App\Http\Controllers\DiscountManagement\DiscountController;
use App\Http\Controllers\ProfitRatiosController;
use App\Http\Controllers\Reports\ReportController;
use App\Http\Controllers\UnifiedOrderController;
use App\Http\Controllers\UserManagementControllers\ProviderController;
use App\Http\Controllers\UserManagementControllers\UserManagementController;

/*
|--------------------------------------------------------------------------
| Authentication Routes
|--------------------------------------------------------------------------
*/

Route::post('register', [UserManagementController::class, 'register']);
Route::post('confirm-registration', [UserManagementController::class, 'confirmRegistration']);

// تسجيل الدخول والتأكيد
Route::post('login', [UserManagementController::class, 'login']);
Route::post('confirm-login', [UserManagementController::class, 'confirmLogin']);
Route::post('refresh', [UserManagementController::class, 'refreshToken']);


// نسيان كلمة المرور (منفصل)
Route::post('forgot-password', [UserManagementController::class, 'forgotPassword']);
Route::post('confirm-forgot-password', [UserManagementController::class, 'confirmForgotPassword']);
Route::post('reset-password', [UserManagementController::class, 'resetPassword']);

// إعادة إرسال OTP
Route::post('resend-otp', [UserManagementController::class, 'resendOTP']);

Route::middleware('auth:sanctum')->post('/logout', [UserManagementController::class, 'logout']);
Route::middleware('auth:sanctum')->delete('/account/delete', [UserManagementController::class, 'deleteAccount']);

/*
|--------------------------------------------------------------------------
| Forget Password Routes
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| Authenticated Routes
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum'])->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Category and SubCategory Routes
    |--------------------------------------------------------------------------
    */

    // categories routes
    Route::apiResource('/categories', CategoryController::class);

    // for admin list when addding categories and subcategories
    Route::post('/categories/subcategories', [CategoryController::class, 'getSubCategories']);

    // subcategories routes
    Route::apiResource('/subcategories', SubCategoryController::class);
    // Get attributes for product form (used by store app when adding product)
    Route::get('/subcategories/{subCategoryId}/attributes', [SubCategoryController::class, 'getAttributesForProduct']);

    /** service provider */
    Route::apiResource('/providers', ProviderController::class);
    Route::get('/provider/profile', [ProviderController::class, 'profile']);
    Route::put('/provider/profile', [ProviderController::class, 'updateProviderProfile']);

    Route::apiResource('/ads', AdsController::class);

    /** discount routes */
    Route::apiResource('/discounts', DiscountController::class);
    Route::apiResource('/coupons', CouponController::class);

    Route::apiResource('/stores', StoreController::class);
    Route::get('/store/profile', [StoreController::class, 'profile']);
    Route::put('/store/profile', [StoreController::class, 'updateStoreProfile']);
    Route::get('/store/categories', [StoreController::class, 'getStoreCategories']);
    Route::get('/store/categories/{category_id}/subcategories', [StoreController::class, 'getStoreSubCategories']);
    Route::get('/stores-list', [StoreController::class, 'listOfStores']);
    Route::get('/stores/category/{category_id}', [StoreController::class, 'getStoresByCategory']);
    Route::get('/store/categories/{store_id}', [StoreController::class, 'getCategoriesOfStore']);
    Route::get('/store/subcategories/{store_id}/{category_id}', [StoreController::class, 'getSubCategoriesOfStore']);
    Route::get('/store/subcategories/{store_id}/{subcategory_id}/products', [StoreController::class, 'getStoreProductsBySubcategory']);
    Route::get('/store/{store_id}/products', [StoreController::class, 'showAllProducts']);


    Route::apiResource('/ratings', RatingController::class);

    Route::apiResource('/vehicle-types', VehicleTypeController::class);

    Route::apiResource('/drivers', DriverController::class);
    Route::get('/driver/profile', [DriverController::class, 'profile']);
    Route::put('/driver/profile', [DriverController::class, 'updateDriverProfile']);
    Route::post('/driver/toggle-active-status', [DriverController::class, 'toggleActiveStatus']);

    // Public (logged-in) user: list accepted products
    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/products/{product}', [ProductController::class, 'show']);
    Route::post('/products/check-stock', [ProductController::class, 'checkVariantStock']);

    // Store Owner
    Route::get('/my-products', [ProductController::class, 'myProducts']);
    Route::post('/products', [ProductController::class, 'store']);
    Route::put('/products/{product}', [ProductController::class, 'update']);
    Route::delete('/products/{product}', [ProductController::class, 'destroy']);
    Route::delete('/product-image/{image}', [ProductController::class, 'deleteImage']);
    Route::get('/stores/{store_id}/products', [ProductController::class, 'getStoreProductsBySubcategory']);


    // Admin
    // Route::middleware('is_admin')->group(function () {
    // Pending products that need admin approval
    Route::get('/pending-products', [ProductController::class, 'pendingProducts']);
    // Accept a pending product
    Route::post('/accept-product/{product}', [ProductController::class, 'acceptProduct']);
    // });


    Route::apiResource('/attributes', AttributeController::class);
    Route::get('/attribute/value/{attribute}', [AttributeController::class, 'getValue']);
    Route::put('/attribute/value/{attributevalue}', [AttributeController::class, 'updateValue']);
    Route::delete('/attribute/value/{attributevalue}', [AttributeController::class, 'destroyValue']);


    Route::delete('/transactions/all', [TransactionController::class, 'deleteAllTansactions']);
    Route::delete('/transactions/provider/{provider}', [TransactionController::class, 'deleteAllProviderTansactions']);
    Route::apiResource('/transactions', TransactionController::class);

    /** Wallet routes */
    Route::get('/my-wallet', [WalletController::class, 'getWallet']);
    Route::post('/wallet/add-balance', [WalletController::class, 'addBalance']);

    /** user routes */

    Route::get('/user/profile', [UserManagementController::class, 'profile']);
    Route::put('/user/profile', [UserManagementController::class, 'updateProfile']);

    // User management (exclude admins) - list, details, delete
    Route::get('/users', [UserManagementController::class, 'listUsers']);
    Route::get('/users/{id}', [UserManagementController::class, 'userDetails']);
    Route::delete('/users/{id}', [UserManagementController::class, 'deleteUser']);



    Route::apiResource('/complaint', ComplaintController::class)->except('update', 'destroy');

    Route::put('/profit-ratios/update-all', [ProfitRatiosController::class, 'updateAll']);
    Route::get('/profit-ratios', [ProfitRatiosController::class, 'index']);

    Route::apiResource('/complaint',ComplaintController::class)->except('update','destroy');

    /*
    |--------------------------------------------------------------------------
    | Cart Routes
    |--------------------------------------------------------------------------
    */
    Route::prefix('cart')->group(function () {
        Route::get('/', [CartController::class, 'index']);           // Get cart
        Route::get('/summary', [CartController::class, 'summary']);  // Quick summary
        Route::post('/items', [CartController::class, 'addItem']);   // Add item
        Route::put('/items/{itemId}', [CartController::class, 'updateItem']);   // Update quantity
        Route::delete('/items/{itemId}', [CartController::class, 'removeItem']); // Remove item
        Route::delete('/clear', [CartController::class, 'clear']);   // Clear cart
        Route::get('/validate', [CartController::class, 'validate']); // Validate before checkout
    });

    /** Report routes */
    Route::get('/reports/statics', [ReportController::class, 'staticsReport']);

    /*
    |--------------------------------------------------------------------------
    | Checkout Routes
    |--------------------------------------------------------------------------
    */
    Route::prefix('checkout')->group(function () {
        Route::get('/preview', [CheckoutController::class, 'preview']);           // معاينة الطلب
        Route::post('/validate-coupon', [CheckoutController::class, 'validateCoupon']); // التحقق من الكوبون
        Route::post('/', [CheckoutController::class, 'checkout']);                  // إتمام الشراء
    });


    /*
    |--------------------------------------------------------------------------
    | Unified Order Routes - جميع الطلبات موحدة (عادية + خاصة)
    |--------------------------------------------------------------------------
    */
    Route::get('/all-orders', [UnifiedOrderController::class, 'userOrders']); // طلبات المستخدم (الكل)

    /*
    |--------------------------------------------------------------------------
    | Order Routes (للمستخدم) - الطلبات العادية فقط
    |--------------------------------------------------------------------------
    */
    Route::prefix('orders')->group(function () {
        Route::get('/', [OrderController::class, 'index']);           // جلب طلبات المستخدم
        Route::get('/{id}', [OrderController::class, 'show']);        // تفاصيل طلب
        Route::post('/{id}/cancel', [OrderController::class, 'cancel']); // إلغاء طلب
        Route::post('/{id}/resend', [OrderController::class, 'resendToDrivers']); // إعادة إرسال للسائقين
        Route::post('/{id}/retry-delivery', [OrderController::class, 'retryDelivery']); // إعادة محاولة بعد الإلغاء
        Route::post('/{id}/reorder', [OrderController::class, 'reorder']); // إعادة طلب (Reorder)
    });

    /*
    |--------------------------------------------------------------------------
    | Custom Order Routes - اطلب أي شيء (للمستخدم) - الطلبات الخاصة فقط
    |--------------------------------------------------------------------------
    */
    Route::prefix('custom-orders')->group(function () {
        Route::get('/', [CustomOrderController::class, 'index']);              // جلب الطلبات الخاصة
        Route::post('/', [CustomOrderController::class, 'store']);             // إنشاء طلب خاص (معلق مباشرة)
        Route::post('/calculate-fee', [CustomOrderController::class, 'calculateFee']); // حساب سعر التوصيل
        Route::get('/{id}', [CustomOrderController::class, 'show']);           // تفاصيل طلب
        Route::post('/{id}/cancel', [CustomOrderController::class, 'cancel']); // إلغاء طلب
        Route::post('/{id}/resend', [CustomOrderController::class, 'resendToDrivers']); // إعادة إرسال للسائقين
        Route::post('/{id}/retry-delivery', [CustomOrderController::class, 'retryDelivery']); // إعادة محاولة بعد الإلغاء
    });

    /*
    |--------------------------------------------------------------------------
    | Driver Unified Routes - جميع طلبات السائق موحدة (عادية + خاصة)
    |--------------------------------------------------------------------------
    */
    Route::prefix('driver/all-orders')->group(function () {
        Route::get('/available', [UnifiedOrderController::class, 'availableOrdersForDriver']); // كل الطلبات المتاحة
        Route::get('/my', [UnifiedOrderController::class, 'driverOrders']);                    // كل طلبات السائق
    });

    /*
    |--------------------------------------------------------------------------
    | Driver Order Routes (للسائق) - الطلبات العادية فقط
    |--------------------------------------------------------------------------
    */
    Route::prefix('driver/orders')->group(function () {
        Route::get('/available', [OrderController::class, 'availableOrders']); // الطلبات المتاحة للتوصيل
        Route::get('/my', [OrderController::class, 'driverOrders']);           // طلبات السائق
        Route::post('/{id}/accept', [OrderController::class, 'acceptOrder']);  // قبول طلب وبدء التوصيل
        Route::post('/{id}/deliver', [OrderController::class, 'deliverOrder']); // تأكيد التوصيل
        Route::post('/{id}/cancel', [OrderController::class, 'driverCancelScheduledOrder']); // إلغاء طلب مجدول (فقط)
    });

    /*
    |--------------------------------------------------------------------------
    | Driver Custom Order Routes - اطلب أي شيء (للسائق) - الطلبات الخاصة فقط
    |--------------------------------------------------------------------------
    */
    Route::prefix('driver/custom-orders')->group(function () {
        Route::get('/available', [CustomOrderController::class, 'availableOrders']); // الطلبات الخاصة المتاحة
        Route::get('/my', [CustomOrderController::class, 'driverOrders']);           // طلبات السائق الخاصة
        Route::post('/{id}/accept', [CustomOrderController::class, 'acceptOrder']);  // قبول طلب وبدء التوصيل
        Route::post('/{id}/deliver', [CustomOrderController::class, 'deliverOrder']); // تأكيد التوصيل
        Route::post('/{id}/cancel', [CustomOrderController::class, 'driverCancelScheduledOrder']); // إلغاء طلب مجدول (فقط)
    });

    /*
    |--------------------------------------------------------------------------
    | Admin Unified Routes - جميع الطلبات للإدارة (عادية + خاصة)
    |--------------------------------------------------------------------------
    */
    Route::get('/admin/all-orders', [UnifiedOrderController::class, 'allOrders']); // كل الطلبات للأدمن

    /*
    |--------------------------------------------------------------------------
    | Admin Order Routes (للإدارة) - الطلبات العادية فقط
    |--------------------------------------------------------------------------
    */
    Route::prefix('admin/orders')->group(function () {
        Route::get('/', [OrderController::class, 'allOrders']);                      // كل الطلبات
        Route::post('/{id}/cancel', [OrderController::class, 'adminCancelOrder']);   // إلغاء طلب (إدارة فقط)
    });

    Route::prefix('admin/custom-orders')->group(function () {
        Route::get('/', [CustomOrderController::class, 'allOrders']);                      // كل الطلبات الخاصة
        Route::post('/{id}/cancel', [CustomOrderController::class, 'adminCancelOrder']);   // إلغاء طلب (إدارة فقط)
    });

});

