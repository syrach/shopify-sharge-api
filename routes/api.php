<?php

use App\Http\Controllers\OrderController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

# Order Management & Error Fix
Route::prefix('orders')->group(function () {
    Route::post('update', [OrderController::class, 'update'])->name('order.update');
});
