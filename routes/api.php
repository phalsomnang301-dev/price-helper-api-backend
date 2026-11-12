<?php

use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ProductController;
use Illuminate\Support\Facades\Route;

Route::post('products/{product}/image', [ProductController::class, 'uploadImage']);

Route::apiResource('categories', CategoryController::class);
Route::apiResource('products', ProductController::class);