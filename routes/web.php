<?php

use Illuminate\Support\Facades\Route;
use App\Models\Product;

Route::get('/', function () {
    return view('welcome');
});



Route::get('/products', function () {
    return Product::with('category', 'prices')->get();
});