<?php

use App\Http\Controllers\Api\Admin\ProductController as ApiAdminProductController;
use Illuminate\Support\Facades\Route;

Route::middleware('admin.api')->prefix('admin')->name('api.admin.')->group(function () {
    Route::patch('/products/{product}', [ApiAdminProductController::class, 'update'])->name('products.update');
});
