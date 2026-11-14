<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ProductController;
use Illuminate\Support\Facades\Route;

// សាធារណៈ: អ្នកណាក៏ហៅបាន
Route::post('login', [AuthController::class, 'login']);

// ការពារ: ត្រូវមាន Token
Route::middleware('auth:sanctum')->group(function () {
    Route::post('logout', [AuthController::class, 'logout']);

    Route::post('products/{product}/image', [ProductController::class, 'uploadImage']);
    Route::apiResource('categories', CategoryController::class)->except(['show']);
    Route::apiResource('products', ProductController::class);
});