<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Categories\CategoryController;
use App\Http\Controllers\Categories\SubCategoryController;
use App\Http\Controllers\UserManagementControllers\UserManagementController;


/*
|--------------------------------------------------------------------------
| Authentication Routes
|--------------------------------------------------------------------------
*/
Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
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
Route::middleware(['auth:api'])->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Category and SubCategory Routes
    |--------------------------------------------------------------------------
    */
    Route::apiResource('/categories', CategoryController::class);
    Route::apiResource('/subcategories', SubCategoryController::class);

});