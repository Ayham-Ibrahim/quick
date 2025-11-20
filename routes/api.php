<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Categories\CategoryController;
use App\Http\Controllers\Categories\SubCategoryController;
use App\Http\Controllers\AdsController;
use App\Http\Controllers\UserManagementControllers\ProviderController;
use App\Http\Controllers\UserManagementControllers\UserManagementController;

/*
|--------------------------------------------------------------------------
| Authentication Routes
|--------------------------------------------------------------------------
*/
Route::post('/register', [UserManagementController::class, 'register']);
Route::post('/login', [UserManagementController::class, 'login']);

Route::middleware('auth:sanctum')->post('/logout', [UserManagementController::class, 'logout']);

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
    // subcategories routes
    Route::apiResource('/subcategories', SubCategoryController::class);

    /** service provider */
    Route::apiResource('/providers', ProviderController::class);
    // Ads routes
    Route::apiResource('/ads', AdsController::class);
 });
