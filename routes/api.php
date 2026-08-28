<?php

use App\Http\Controllers\Api\Admin\AdminUserController as ApiAdminAdminUserController;
use App\Http\Controllers\Api\Admin\BlogPostController as ApiAdminBlogPostController;
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

    // Blog
    Route::get('/blog/categories', [ApiAdminBlogPostController::class, 'categories'])->name('blog.categories');
    Route::get('/blog/posts', [ApiAdminBlogPostController::class, 'index'])->name('blog.posts.index');
    Route::post('/blog/posts', [ApiAdminBlogPostController::class, 'store'])->name('blog.posts.store');
    Route::get('/blog/posts/{post}', [ApiAdminBlogPostController::class, 'show'])->name('blog.posts.show');
    Route::patch('/blog/posts/{post}', [ApiAdminBlogPostController::class, 'update'])->name('blog.posts.update');
    Route::delete('/blog/posts/{post}', [ApiAdminBlogPostController::class, 'destroy'])->name('blog.posts.destroy');

    // Orders
    Route::post('/orders', [ApiAdminOrderController::class, 'createDraft'])->name('orders.store');
    Route::patch('/orders/{order}', [ApiAdminOrderController::class, 'updateDraft'])->name('orders.update');

    // Admin users
    Route::get('/admins', [ApiAdminAdminUserController::class, 'index'])->name('admins.index');
    Route::patch('/admins/{admin}', [ApiAdminAdminUserController::class, 'update'])->name('admins.update');
});
