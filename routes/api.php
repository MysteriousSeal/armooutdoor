<?php

use App\Http\Controllers\Api\Admin\OrderController as ApiAdminOrderController;
use App\Http\Controllers\Api\Admin\ProductController as ApiAdminProductController;
use Illuminate\Support\Facades\Route;

Route::middleware('admin.api')->prefix('admin')->name('api.admin.')->group(function () {
    Route::get('/products/{product}', [ApiAdminProductController::class, 'show'])->name('products.show');
    Route::patch('/products/{product}', [ApiAdminProductController::class, 'update'])->name('products.update');
    Route::post('/orders', [ApiAdminOrderController::class, 'createDraft'])->name('orders.store');
    Route::patch('/orders/{order}', [ApiAdminOrderController::class, 'updateDraft'])->name('orders.update');
});
