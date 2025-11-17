<?php

use App\Http\Controllers\UserManagementControllers\UserManagementController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
Route::post('/register', [UserManagementController::class, 'register']);
Route::post('/login', [UserManagementController::class, 'login']);

Route::middleware('auth:sanctum')->post('/logout', [UserManagementController::class, 'logout']);
