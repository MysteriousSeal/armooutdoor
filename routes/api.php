<?php

use App\Http\Controllers\Api\Admin\CategoryController as ApiAdminCategoryController;
use App\Http\Controllers\Api\Admin\OrderController as ApiAdminOrderController;
use App\Http\Controllers\Api\Admin\ProductController as ApiAdminProductController;
use Illuminate\Support\Facades\Route;

// `throttle` d'abord : une tentative au jeton invalide doit être comptée,
// sinon la porte se laisse forcer aussi vite que le réseau le permet.
Route::middleware(['throttle:admin-api', 'admin.api'])->prefix('admin')->name('api.admin.')->group(function () {
    // Categories
    Route::get('/categories', [ApiAdminCategoryController::class, 'index'])->name('categories.index');

    // Products
    Route::get('/products', [ApiAdminProductController::class, 'index'])->name('products.index');
    Route::post('/products', [ApiAdminProductController::class, 'store'])->name('products.store');
    Route::get('/products/{product}', [ApiAdminProductController::class, 'show'])->name('products.show');
    Route::patch('/products/{product}', [ApiAdminProductController::class, 'update'])->name('products.update');

    // Orders
    Route::post('/orders', [ApiAdminOrderController::class, 'createDraft'])->name('orders.store');
    Route::patch('/orders/{order}', [ApiAdminOrderController::class, 'updateDraft'])->name('orders.update');
});
