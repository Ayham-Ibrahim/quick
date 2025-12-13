<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AdsController;
use App\Http\Controllers\StoreController;
use App\Http\Controllers\RatingController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\Categories\CategoryController;
use App\Http\Controllers\Categories\SubCategoryController;
use App\Http\Controllers\DriverController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\UserManagementControllers\ProviderController;
use App\Http\Controllers\UserManagementControllers\UserManagementController;
use App\Http\Controllers\VehicleTypeController;
use App\Http\Controllers\WalletController;

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

    /** service provider */
    Route::apiResource('/providers', ProviderController::class);
    Route::get('/provider/profile', [ProviderController::class, 'profile']);
    Route::put('/provider/profile', [ProviderController::class, 'updateProviderProfile']);

    Route::apiResource('/ads', AdsController::class);

    Route::apiResource('/stores', StoreController::class);
    Route::get('/store/profile', [StoreController::class, 'profile']);
    Route::put('/store/profile', [StoreController::class, 'updateStoreProfile']);
    Route::get('/store/categories', [StoreController::class, 'getStoreCategories']);
    Route::get('/store/categories/{category_id}/subcategories', [StoreController::class, 'getStoreSubCategories']);




    Route::apiResource('/ratings', RatingController::class);

    Route::apiResource('/vehicle-types', VehicleTypeController::class);

    Route::apiResource('/drivers', DriverController::class);
    Route::get('/driver/profile', [DriverController::class, 'profile']);
    Route::put('/driver/profile', [DriverController::class, 'updateDriverProfile']);

    // Public (logged-in) user: list accepted products
    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/products/{product}', [ProductController::class, 'show']);

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

    Route::delete('/transactions/all', [TransactionController::class, 'deleteAllTansactions']);
    Route::delete('/transactions/provider/{provider}', [TransactionController::class, 'deleteAllProviderTansactions']);
    Route::apiResource('/transactions', TransactionController::class);

    /** Wallet routes */
    Route::get('/my-wallet', [WalletController::class, 'getWallet']);
    Route::post('/wallet/add-balance', [WalletController::class, 'addBalance']);
});
