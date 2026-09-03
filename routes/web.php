<?php

// Account (customer profile, addresses)
use App\Http\Controllers\Account\AccountController;
use App\Http\Controllers\Account\AddressController;
use App\Http\Controllers\Account\ConversationController as AccountConversationController;
use App\Http\Controllers\Account\DiscountCodeController as AccountDiscountCodeController;
use App\Http\Controllers\Account\IdentityDocumentController as AccountIdentityDocumentController;
// Admin (back office)
use App\Http\Controllers\Account\ProfileController;
use App\Http\Controllers\Admin\AccountingController as AdminAccountingController;
use App\Http\Controllers\Admin\ActivityController as AdminActivityController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\AnalyticsController as AdminAnalyticsController;
use App\Http\Controllers\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Admin\BackupController as AdminBackupController;
use App\Http\Controllers\Admin\BlogPostController as AdminBlogPostController;
use App\Http\Controllers\Admin\CarrierPriceTierController as AdminCarrierPriceTierController;
use App\Http\Controllers\Admin\CartController as AdminCartController;
use App\Http\Controllers\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Admin\ChangelogController as AdminChangelogController;
use App\Http\Controllers\Admin\CompanySettingController as AdminCompanySettingController;
use App\Http\Controllers\Admin\ConversationController as AdminConversationController;
use App\Http\Controllers\Admin\CustomerController as AdminCustomerController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\DiscountCodeController as AdminDiscountCodeController;
use App\Http\Controllers\Admin\DiscountController as AdminDiscountController;
use App\Http\Controllers\Admin\IdentityDocumentController as AdminIdentityDocumentController;
use App\Http\Controllers\Admin\InvoiceSettingController as AdminInvoiceSettingController;
use App\Http\Controllers\Admin\ProductSettingController as AdminProductSettingController;
use App\Http\Controllers\Admin\LabelController as AdminLabelController;
use App\Http\Controllers\Admin\MarketplaceController as AdminMarketplaceController;
use App\Http\Controllers\Admin\MarketplaceListingController as AdminMarketplaceListingController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\PackageTypeController as AdminPackageTypeController;
use App\Http\Controllers\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Admin\PurchaseOrderController as AdminPurchaseOrderController;
use App\Http\Controllers\Admin\ReviewController as AdminReviewController;
use App\Http\Controllers\Admin\SearchController as AdminSearchController;
use App\Http\Controllers\Admin\SettingsController as AdminSettingsController;
use App\Http\Controllers\Admin\ShippingSettingController as AdminShippingSettingController;
use App\Http\Controllers\Admin\StripePaymentController as AdminStripePaymentController;
use App\Http\Controllers\Admin\SupplierController as AdminSupplierController;
// Auth (customer-facing login/register/password reset)
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\ResetPasswordController;
// Storefront (shop, cart, checkout, orders, etc.)
use App\Http\Controllers\AllProductsController;
use App\Http\Controllers\BestSellersController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\FaqController;
use App\Http\Controllers\GuestConversationController;
use App\Http\Controllers\HelpController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\NewArrivalsController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PreferenceController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\PromotionsController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\StripeWebhookController;
use App\Http\Controllers\WishlistController;
use App\Models\BlogCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Sitemap
|--------------------------------------------------------------------------
*/

Route::get('/robots.txt', [SitemapController::class, 'robots'])->name('robots');
Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap.index');
Route::get('/sitemap-pages.xml', [SitemapController::class, 'pages'])->name('sitemap.pages');
Route::get('/sitemap-categories.xml', [SitemapController::class, 'categories'])->name('sitemap.categories');
Route::get('/sitemap-products.xml', [SitemapController::class, 'products'])->name('sitemap.products');
Route::get('/sitemap-blog.xml', [SitemapController::class, 'blog'])->name('sitemap.blog');
Route::get('/plan-du-site', [SitemapController::class, 'html'])->name('sitemap.html');

/*
|--------------------------------------------------------------------------
| Preferences (theme, etc. — no auth required)
|--------------------------------------------------------------------------
*/

Route::post('/preferences/theme', [PreferenceController::class, 'theme'])->name('preferences.theme');

/*
|--------------------------------------------------------------------------
| Admin (back office)
|--------------------------------------------------------------------------
| Login is public; everything else sits behind the `admin` middleware.
*/

Route::prefix(config('shop.admin_path'))->name('admin.')->group(function () {
    Route::get('/', [AdminAuthController::class, 'create'])->name('login');
    Route::post('/login', [AdminAuthController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('login.store');

    Route::middleware('admin')->group(function () {
        Route::post('/logout', [AdminAuthController::class, 'destroy'])->name('logout');
        Route::get('/dashboard', AdminDashboardController::class)->name('dashboard');
        Route::get('/changelog', AdminChangelogController::class)->name('changelog');
        Route::get('/search', [AdminSearchController::class, 'index'])->name('search');
        Route::get('/analytics', [AdminAnalyticsController::class, 'index'])->name('analytics.index');
        Route::get('/analytics/active-now', [AdminAnalyticsController::class, 'activeNow'])->name('analytics.active-now');
        // Owner only, like the rest of what touches money: these pages will
        // carry revenue and costs.
        // Identity documents are readable by owners alone, and every look is
        // written to the activity log.
        Route::middleware('admin.owner')->group(function (): void {
            Route::get('/documents', [AdminIdentityDocumentController::class, 'index'])->name('documents.index');
            Route::get('/documents/{document}', [AdminIdentityDocumentController::class, 'show'])->name('documents.show');
            Route::patch('/documents/{document}', [AdminIdentityDocumentController::class, 'review'])->name('documents.review');
        });

        Route::middleware('admin.owner')->group(function (): void {
            Route::get('/accounting/sales', [AdminAccountingController::class, 'sales'])->name('accounting.sales');
            Route::get('/accounting/sales/{month}', [AdminAccountingController::class, 'salesMonth'])
                ->where('month', '\d{4}-\d{2}')
                ->name('accounting.sales.month');
            Route::get('/accounting/purchases', [AdminAccountingController::class, 'purchases'])->name('accounting.purchases');
            Route::get('/accounting/purchases/{month}', [AdminAccountingController::class, 'purchasesMonth'])
                ->where('month', '\d{4}-\d{2}')
                ->name('accounting.purchases.month');
            Route::get('/accounting/purchases/{month}/pdf', [AdminAccountingController::class, 'purchasesPdf'])
                ->where('month', '\d{4}-\d{2}')
                ->name('accounting.purchases.pdf');

            Route::get('/accounting/sales/{month}/pdf', [AdminAccountingController::class, 'salesPdf'])
                ->where('month', '\d{4}-\d{2}')
                ->name('accounting.sales.pdf');

            // The hand-written entries, inside the month being read.
            Route::post('/accounting/{section}/{month}/entries', [AdminAccountingController::class, 'storeEntry'])
                ->where(['section' => 'sales|purchases', 'month' => '\d{4}-\d{2}'])
                ->name('accounting.entries.store');
            Route::put('/accounting/{section}/{month}/entries/{entry}', [AdminAccountingController::class, 'updateEntry'])
                ->where(['section' => 'sales|purchases', 'month' => '\d{4}-\d{2}'])
                ->name('accounting.entries.update');
            // The supplier's invoice, attached to the line it paid for. The
            // file has no public URL: it is read off the private disk here,
            // behind the same owner-only door as the rest of the accounts.
            Route::post('/accounting/{section}/{month}/entries/{entry}/invoice', [AdminAccountingController::class, 'storeInvoiceFile'])
                ->where(['section' => 'sales|purchases', 'month' => '\d{4}-\d{2}'])
                ->name('accounting.entries.invoice.store');
            Route::get('/accounting/{section}/{month}/entries/{entry}/invoice', [AdminAccountingController::class, 'showInvoiceFile'])
                ->where(['section' => 'sales|purchases', 'month' => '\d{4}-\d{2}'])
                ->name('accounting.entries.invoice.show');
            Route::delete('/accounting/{section}/{month}/entries/{entry}/invoice', [AdminAccountingController::class, 'destroyInvoiceFile'])
                ->where(['section' => 'sales|purchases', 'month' => '\d{4}-\d{2}'])
                ->name('accounting.entries.invoice.destroy');

            Route::delete('/accounting/{section}/{month}/entries/{entry}', [AdminAccountingController::class, 'destroyEntry'])
                ->where(['section' => 'sales|purchases', 'month' => '\d{4}-\d{2}'])
                ->name('accounting.entries.destroy');
        });

        // Owner only, like the accounts: an archive holds every order and
        // every customer's address.
        Route::middleware('admin.owner')->group(function (): void {
            Route::get('/backups', [AdminBackupController::class, 'index'])->name('backups.index');
            Route::post('/backups', [AdminBackupController::class, 'store'])->name('backups.store');
            Route::get('/backups/{name}', [AdminBackupController::class, 'show'])->name('backups.show');
            Route::delete('/backups/{name}', [AdminBackupController::class, 'destroy'])->name('backups.destroy');
        });

        Route::get('/activity', [AdminActivityController::class, 'index'])->name('activity');
        Route::get('/customers', [AdminCustomerController::class, 'index'])->name('customers.index');
        Route::get('/customers/export', [AdminCustomerController::class, 'export'])->name('customers.export');
        Route::get('/customers/{customer}', [AdminCustomerController::class, 'show'])->name('customers.show');
        Route::patch('/customers/{customer}/notes', [AdminCustomerController::class, 'updateNotes'])->name('customers.notes.update');
        Route::patch('/customers/{customer}', [AdminCustomerController::class, 'updateAccount'])->name('customers.update');
        Route::post('/customers/{customer}/send-reset-link', [AdminCustomerController::class, 'sendResetLink'])->name('customers.send-reset-link');
        Route::patch('/customers/{customer}/ban', [AdminCustomerController::class, 'ban'])->middleware('admin.owner')->name('customers.ban');
        Route::patch('/customers/{customer}/unban', [AdminCustomerController::class, 'unban'])->middleware('admin.owner')->name('customers.unban');
        Route::get('/conversations', [AdminConversationController::class, 'index'])->name('conversations.index');
        Route::get('/conversations/{conversation}', [AdminConversationController::class, 'show'])->name('conversations.show');
        Route::post('/conversations/{conversation}/reply', [AdminConversationController::class, 'reply'])->name('conversations.reply');
        Route::patch('/conversations/{conversation}/messages/{message}', [AdminConversationController::class, 'updateMessage'])->name('conversations.messages.update');
        Route::patch('/conversations/{conversation}/close', [AdminConversationController::class, 'close'])->name('conversations.close');
        Route::patch('/conversations/{conversation}/reopen', [AdminConversationController::class, 'reopen'])->name('conversations.reopen');

        // Products
        Route::get('/products', [AdminProductController::class, 'index'])->name('products.index');
        Route::get('/products/export', [AdminProductController::class, 'export'])->name('products.export');
        Route::get('/products/create', [AdminProductController::class, 'create'])->name('products.create');
        Route::post('/products', [AdminProductController::class, 'store'])->name('products.store');
        Route::get('/products/{product}/edit', [AdminProductController::class, 'edit'])->name('products.edit');
        // The cover as a JPEG: the shop stores WebP, which no marketplace form
        // or supplier wants.
        Route::get('/products/{product}/cover.jpg', [AdminProductController::class, 'coverImage'])->name('products.cover');
        Route::get('/products/{product}/stock-history', [AdminProductController::class, 'stockHistory'])->name('products.stock-history');
        // One label per article: a plain product, or one variant of a product
        // that has them.
        // The list of every article that could wear a label.
        Route::get('/labels', [AdminLabelController::class, 'index'])->name('labels.index');
        Route::put('/labels/{product}', [AdminLabelController::class, 'update'])->name('labels.update');
        Route::get('/products/{product}/label', [AdminProductController::class, 'label'])->name('products.label');
        Route::get('/products/{product}/variants/{variant}/label', [AdminProductController::class, 'label'])->name('products.variants.label');
        Route::get('/products/{product}/average-cost', [AdminProductController::class, 'averageCost'])->name('products.average-cost');
        Route::put('/products/{product}', [AdminProductController::class, 'update'])->name('products.update');
        Route::patch('/products/{product}/status', [AdminProductController::class, 'toggleStatus'])->name('products.status');
        Route::patch('/products/{product}/supplier-availability', [AdminProductController::class, 'toggleSupplierAvailability'])->name('products.supplier-availability');
        Route::patch('/products/{product}/quantity', [AdminProductController::class, 'updateQuantity'])->name('products.quantity');
        Route::patch('/products/{product}/supplier', [AdminProductController::class, 'updateSupplier'])->name('products.supplier');

        // Marketplaces
        Route::get('/marketplaces', [AdminMarketplaceListingController::class, 'index'])->name('marketplaces.index');
        Route::get('/marketplaces/naturabuy', [AdminMarketplaceListingController::class, 'naturabuy'])->name('marketplaces.naturabuy');
        Route::post('/marketplaces/naturabuy/sync', [AdminMarketplaceListingController::class, 'syncNaturabuy'])->name('marketplaces.naturabuy.sync');

        // Blog
        Route::get('/blog', [AdminBlogPostController::class, 'index'])->name('blog.index');
        Route::get('/blog/create', [AdminBlogPostController::class, 'create'])->name('blog.create');
        Route::post('/blog', [AdminBlogPostController::class, 'store'])->name('blog.store');
        Route::post('/blog/images', [AdminBlogPostController::class, 'uploadBodyImage'])->name('blog.images');
        Route::get('/blog/{post}/edit', [AdminBlogPostController::class, 'edit'])->name('blog.edit');
        Route::put('/blog/{post}', [AdminBlogPostController::class, 'update'])->name('blog.update');
        Route::delete('/blog/{post}', [AdminBlogPostController::class, 'destroy'])->name('blog.destroy');

        // Customer reviews, read across the whole catalogue at once
        Route::get('/reviews', [AdminReviewController::class, 'index'])->name('reviews.index');
        Route::post('/reviews', [AdminReviewController::class, 'store'])->name('reviews.store');
        Route::delete('/reviews/{review}', [AdminReviewController::class, 'destroy'])->middleware('admin.owner')->name('reviews.destroy');

        // Product discounts (sale price on a single product, no code needed)
        Route::get('/discounts', [AdminDiscountController::class, 'index'])->name('discounts.index');
        Route::get('/discounts/create', [AdminDiscountController::class, 'create'])->name('discounts.create');
        Route::post('/discounts', [AdminDiscountController::class, 'store'])->name('discounts.store');
        Route::get('/discounts/{discount}/edit', [AdminDiscountController::class, 'edit'])->name('discounts.edit');
        Route::put('/discounts/{discount}', [AdminDiscountController::class, 'update'])->name('discounts.update');
        Route::delete('/discounts/{discount}', [AdminDiscountController::class, 'destroy'])->middleware('admin.owner')->name('discounts.destroy');

        // Discount codes (cart-wide coupon codes)
        Route::get('/discount-codes/check-code', [AdminDiscountCodeController::class, 'checkCode'])->name('discount-codes.check-code');
        Route::get('/discount-codes/create', [AdminDiscountCodeController::class, 'create'])->name('discount-codes.create');
        Route::post('/discount-codes', [AdminDiscountCodeController::class, 'store'])->name('discount-codes.store');
        Route::get('/discount-codes/{discountCode}/edit', [AdminDiscountCodeController::class, 'edit'])->name('discount-codes.edit');
        Route::get('/discount-codes/{discountCode}/label', [AdminDiscountCodeController::class, 'label'])->name('discount-codes.label');
        Route::put('/discount-codes/{discountCode}', [AdminDiscountCodeController::class, 'update'])->name('discount-codes.update');
        Route::delete('/discount-codes/{discountCode}', [AdminDiscountCodeController::class, 'destroy'])->middleware('admin.owner')->name('discount-codes.destroy');

        // Categories
        Route::get('/categories', [AdminCategoryController::class, 'index'])->name('categories.index');
        Route::get('/categories/create', [AdminCategoryController::class, 'create'])->name('categories.create');
        Route::post('/categories', [AdminCategoryController::class, 'store'])->name('categories.store');
        Route::get('/categories/{category}/edit', [AdminCategoryController::class, 'edit'])->name('categories.edit');
        Route::put('/categories/{category}', [AdminCategoryController::class, 'update'])->name('categories.update');
        Route::delete('/categories/{category}', [AdminCategoryController::class, 'destroy'])->name('categories.destroy');

        // Orders
        Route::get('/purchase-orders', [AdminPurchaseOrderController::class, 'index'])->name('purchase-orders.index');
        Route::get('/purchase-orders/create', [AdminPurchaseOrderController::class, 'create'])->name('purchase-orders.create');
        Route::post('/purchase-orders', [AdminPurchaseOrderController::class, 'store'])->name('purchase-orders.store');
        Route::get('/purchase-orders/{purchaseOrder}', [AdminPurchaseOrderController::class, 'show'])->name('purchase-orders.show');
        Route::get('/purchase-orders/{purchaseOrder}/pdf', [AdminPurchaseOrderController::class, 'pdf'])->name('purchase-orders.pdf');
        Route::get('/purchase-orders/{purchaseOrder}/receipt-pdf', [AdminPurchaseOrderController::class, 'receiptPdf'])->name('purchase-orders.receipt-pdf');
        Route::get('/purchase-orders/{purchaseOrder}/edit', [AdminPurchaseOrderController::class, 'edit'])->name('purchase-orders.edit');
        Route::put('/purchase-orders/{purchaseOrder}', [AdminPurchaseOrderController::class, 'update'])->name('purchase-orders.update');
        Route::patch('/purchase-orders/{purchaseOrder}/send', [AdminPurchaseOrderController::class, 'send'])->name('purchase-orders.send');
        Route::post('/purchase-orders/{purchaseOrder}/receive', [AdminPurchaseOrderController::class, 'receive'])->name('purchase-orders.receive');
        // Owner-only, like order refund and delete: cancelling closes out
        // committed stock, and deleting cannot be taken back.
        Route::patch('/purchase-orders/{purchaseOrder}/cancel', [AdminPurchaseOrderController::class, 'cancel'])->middleware('admin.owner')->name('purchase-orders.cancel');
        Route::delete('/purchase-orders/{purchaseOrder}', [AdminPurchaseOrderController::class, 'destroy'])->middleware('admin.owner')->name('purchase-orders.destroy');

        Route::get('/carts', [AdminCartController::class, 'index'])->name('carts.index');

        Route::get('/orders', [AdminOrderController::class, 'index'])->name('orders.index');
        Route::get('/orders/export', [AdminOrderController::class, 'export'])->name('orders.export');
        Route::get('/orders/create', [AdminOrderController::class, 'create'])->name('orders.create');
        Route::post('/orders', [AdminOrderController::class, 'store'])->name('orders.store');
        Route::get('/orders/{order}', [AdminOrderController::class, 'show'])->name('orders.show');
        Route::get('/orders/{order}/edit', [AdminOrderController::class, 'edit'])->name('orders.edit');
        Route::put('/orders/{order}', [AdminOrderController::class, 'update'])->name('orders.update');
        Route::get('/orders/{order}/invoice', [AdminOrderController::class, 'invoice'])->name('orders.invoice');
        Route::get('/orders/{order}/delivery-slip', [AdminOrderController::class, 'deliverySlip'])->name('orders.delivery-slip');
        Route::get('/orders/{order}/address-label', [AdminOrderController::class, 'addressLabel'])->name('orders.address-label');
        Route::patch('/orders/{order}/validate-draft', [AdminOrderController::class, 'validateDraft'])->name('orders.validate-draft');
        Route::patch('/orders/{order}/prepare', [AdminOrderController::class, 'prepare'])->name('orders.prepare');
        Route::patch('/orders/{order}/ship', [AdminOrderController::class, 'ship'])->name('orders.ship');
        Route::patch('/orders/{order}/in-transit', [AdminOrderController::class, 'markInTransit'])->name('orders.in-transit');
        Route::patch('/orders/{order}/deliver', [AdminOrderController::class, 'deliver'])->name('orders.deliver');
        Route::patch('/orders/{order}/refund', [AdminOrderController::class, 'refund'])->middleware('admin.owner')->name('orders.refund');
        // Ouvert à tous les admins, comme la réception d'un bon de commande :
        // remettre en rayon ce qui est physiquement revenu n'a rien d'un geste
        // engageant comme le remboursement lui-même.
        Route::patch('/orders/{order}/items/{item}/restock', [AdminOrderController::class, 'restockItem'])->name('orders.items.restock');
        // Before the {order} routes: /orders/bulk/... must not be taken
        // as an order number by the route-model binding.
        Route::patch('/orders/bulk/archive', [AdminOrderController::class, 'bulkArchive'])->name('orders.bulk-archive');
        Route::patch('/orders/bulk/unarchive', [AdminOrderController::class, 'bulkUnarchive'])->name('orders.bulk-unarchive');
        Route::patch('/orders/{order}/archive', [AdminOrderController::class, 'archive'])->name('orders.archive');
        Route::patch('/orders/{order}/unarchive', [AdminOrderController::class, 'unarchive'])->name('orders.unarchive');
        // Owner-only, like refund rather than like archive: archiving hides an
        // order, but marking one as test moves revenue out of the figures.
        Route::patch('/orders/bulk/test', [AdminOrderController::class, 'bulkMarkTest'])->middleware('admin.owner')->name('orders.bulk-test');
        Route::patch('/orders/bulk/untest', [AdminOrderController::class, 'bulkUnmarkTest'])->middleware('admin.owner')->name('orders.bulk-untest');
        Route::patch('/orders/{order}/test', [AdminOrderController::class, 'markTest'])->middleware('admin.owner')->name('orders.test');
        Route::patch('/orders/{order}/untest', [AdminOrderController::class, 'unmarkTest'])->middleware('admin.owner')->name('orders.untest');
        // Drafts are deleted rather than archived, and deleting cannot be
        // taken back, so it sits with the other owner-only actions.
        Route::delete('/orders/bulk/delete', [AdminOrderController::class, 'bulkDestroy'])->middleware('admin.owner')->name('orders.bulk-destroy');
        Route::delete('/orders/{order}', [AdminOrderController::class, 'destroy'])->middleware('admin.owner')->name('orders.destroy');
        Route::patch('/orders/{order}/tracking', [AdminOrderController::class, 'updateTracking'])->name('orders.tracking.update');
        Route::post('/orders/{order}/discount-code', [AdminOrderController::class, 'createDiscountCode'])->name('orders.discount-code.store');
        Route::patch('/orders/{order}/marketplace-commission', [AdminOrderController::class, 'updateMarketplaceCommission'])->name('orders.marketplace-commission.update');
        Route::patch('/orders/{order}/shipping-paid', [AdminOrderController::class, 'updateShippingPaid'])->name('orders.shipping-paid.update');
        Route::patch('/orders/{order}/shipping-address', [AdminOrderController::class, 'updateShippingAddress'])->name('orders.address.shipping');
        Route::patch('/orders/{order}/billing-address', [AdminOrderController::class, 'updateBillingAddress'])->name('orders.address.billing');

        // Settings
        Route::get('/settings', [AdminSettingsController::class, 'index'])->name('settings.index');
        Route::get('/settings/shipping', [AdminShippingSettingController::class, 'edit'])->name('settings.shipping.edit');
        Route::put('/settings/shipping', [AdminShippingSettingController::class, 'update'])->name('settings.shipping.update');
        Route::post('/settings/package-types', [AdminPackageTypeController::class, 'store'])->name('settings.package-types.store');
        Route::delete('/settings/package-types/{packageType}', [AdminPackageTypeController::class, 'destroy'])->name('settings.package-types.destroy');
        Route::get('/settings/company', [AdminCompanySettingController::class, 'edit'])->name('settings.company.edit');
        Route::put('/settings/company', [AdminCompanySettingController::class, 'update'])->name('settings.company.update');
        Route::get('/settings/invoice', [AdminInvoiceSettingController::class, 'edit'])->name('settings.invoice.edit');
        Route::put('/settings/invoice', [AdminInvoiceSettingController::class, 'update'])->name('settings.invoice.update');
        Route::put('/settings/carriers/{carrier}/price-tiers', [AdminCarrierPriceTierController::class, 'update'])->name('settings.carriers.price-tiers.update');
        Route::get('/settings/products', [AdminProductSettingController::class, 'edit'])->name('settings.products.edit');
        Route::put('/settings/products', [AdminProductSettingController::class, 'update'])->name('settings.products.update');
        Route::get('/settings/orders', [AdminSettingsController::class, 'orders'])->name('settings.orders.edit');
        Route::get('/settings/email', [AdminSettingsController::class, 'email'])->middleware('admin.owner')->name('settings.email');
        Route::post('/settings/email/test', [AdminSettingsController::class, 'sendTestEmail'])->middleware('admin.owner')->name('settings.email.test');
        Route::post('/settings/marketplaces', [AdminMarketplaceController::class, 'store'])->name('settings.marketplaces.store');
        Route::put('/settings/marketplaces/{marketplace}', [AdminMarketplaceController::class, 'update'])->name('settings.marketplaces.update');
        Route::delete('/settings/marketplaces/{marketplace}', [AdminMarketplaceController::class, 'destroy'])->name('settings.marketplaces.destroy');
        Route::get('/settings/suppliers', [AdminSupplierController::class, 'index'])->name('settings.suppliers.index');
        Route::get('/settings/suppliers/create', [AdminSupplierController::class, 'create'])->name('settings.suppliers.create');
        Route::post('/settings/suppliers', [AdminSupplierController::class, 'store'])->name('settings.suppliers.store');
        Route::get('/settings/suppliers/{supplier}/edit', [AdminSupplierController::class, 'edit'])->name('settings.suppliers.edit');
        Route::put('/settings/suppliers/{supplier}', [AdminSupplierController::class, 'update'])->name('settings.suppliers.update');
        Route::delete('/settings/suppliers/{supplier}', [AdminSupplierController::class, 'destroy'])->name('settings.suppliers.destroy');
        Route::middleware('admin.owner')->group(function () {
            Route::get('/settings/admins', [AdminUserController::class, 'index'])->name('settings.admins.index');
            Route::get('/settings/admins/create', [AdminUserController::class, 'create'])->name('settings.admins.create');
            Route::post('/settings/admins', [AdminUserController::class, 'store'])->name('settings.admins.store');
            Route::get('/settings/admins/{admin}/edit', [AdminUserController::class, 'edit'])->name('settings.admins.edit');
            Route::put('/settings/admins/{admin}', [AdminUserController::class, 'update'])->name('settings.admins.update');
            Route::patch('/settings/admins/{admin}/deactivate', [AdminUserController::class, 'deactivate'])->name('settings.admins.deactivate');
            Route::patch('/settings/admins/{admin}/reactivate', [AdminUserController::class, 'reactivate'])->name('settings.admins.reactivate');

            Route::get('/stripe/orphaned-payments', [AdminStripePaymentController::class, 'index'])->name('stripe.orphaned-payments.index');
            Route::post('/stripe/orphaned-payments/{sessionId}/finalize', [AdminStripePaymentController::class, 'finalize'])->name('stripe.orphaned-payments.finalize');
        });
    });
});

/*
|--------------------------------------------------------------------------
| Storefront — public pages
|--------------------------------------------------------------------------
*/

Route::get('/', HomeController::class)->name('home');
Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');
Route::get('/produits', [AllProductsController::class, 'index'])->name('products.all');
Route::get('/nouveautes', [NewArrivalsController::class, 'index'])->name('products.new-arrivals');
Route::get('/promotions', [PromotionsController::class, 'index'])->name('products.promotions');
Route::get('/meilleures-ventes', [BestSellersController::class, 'index'])->name('products.best-sellers');

Route::get('/blog', [BlogController::class, 'index'])->name('blog.index');
// La rubrique passe avant l'article : les deux occupent `/blog/{slug}`, et
// seule celle-ci est contrainte aux slugs de rubriques. Tout le reste retombe
// sur la route article juste en dessous.
Route::get('/blog/{category}', [BlogController::class, 'category'])
    ->where('category', BlogCategory::routeSlugPattern())
    ->name('blog.category');
Route::get('/blog/{slug}', [BlogController::class, 'show'])->name('blog.show');
Route::get('/categories/{category}', [CategoryController::class, 'show'])->name('categories.show');
Route::get('/products/{product:slug}', [ProductController::class, 'show'])
    // Un slug inconnu n'est pas forcément une erreur : c'est peut-être une
    // ancienne adresse du produit, qui doit mener à la nouvelle.
    ->missing(fn (Request $request, $exception) => app(ProductController::class)
        ->movedOrMissing($request, (string) $request->route('product')))
    ->name('products.show');
Route::get('/search', [SearchController::class, 'index'])->name('search');

// Legal pages
Route::view('/cgv', 'legal.terms')->name('legal.terms');
Route::view('/mentions-legales', 'legal.notice')->name('legal.notice');
Route::view('/confidentialite', 'legal.privacy')->name('legal.privacy');
Route::view('/droit-de-retractation', 'legal.withdrawal')->name('legal.withdrawal');
Route::get('/faq', [FaqController::class, 'index'])->name('faq');
Route::get('/contact', [ContactController::class, 'create'])->name('contact.show');
Route::post('/contact', [ContactController::class, 'store'])->middleware('throttle:5,1')->name('contact.store');
// A guest's private thread: the token is the whole key, so the routes accept
// nothing shorter than a real one.
Route::get('/messages/{token}', [GuestConversationController::class, 'show'])
    ->where('token', '[A-Za-z0-9]{48}')
    ->name('guest.conversations.show');
Route::post('/messages/{token}/reply', [GuestConversationController::class, 'reply'])
    ->where('token', '[A-Za-z0-9]{48}')
    ->middleware('throttle:10,1')
    ->name('guest.conversations.reply');
Route::get('/livraison-et-retours', [HelpController::class, 'shippingReturns'])->name('help.shipping-returns');
Route::get('/a-propos', [HelpController::class, 'about'])->name('about');
Route::get('/paiement-securise', [HelpController::class, 'securePayment'])->name('help.secure-payment');

/*
|--------------------------------------------------------------------------
| Debug (local environment only — lets you preview error pages)
|--------------------------------------------------------------------------
*/

if (app()->environment('local')) {
    Route::get('/debug/throw-500', function () {
        abort(500, 'Fake error for testing the 500 page.');
    })->name('debug.throw-500');
}

/*
|--------------------------------------------------------------------------
| Cart (guests and customers alike)
|--------------------------------------------------------------------------
*/

Route::post('/stripe/webhook', [StripeWebhookController::class, 'handle'])->name('stripe.webhook');

Route::get('/cart', [CartController::class, 'show'])->name('cart.show');
Route::post('/cart', [CartController::class, 'add'])->name('cart.add');
Route::patch('/cart/{product:slug}', [CartController::class, 'update'])->name('cart.update');
Route::delete('/cart/{product:slug}', [CartController::class, 'destroy'])->name('cart.remove');

/*
|--------------------------------------------------------------------------
| Customer auth — login, register, password reset
|--------------------------------------------------------------------------
*/

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('login.store');
    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])->name('register.store');

    Route::get('/forgot-password', [ForgotPasswordController::class, 'create'])->name('password.request');
    // Looser than it looks: the controller already holds each address to one
    // send a minute, so this IP-wide cap only guards against bulk abuse.
    Route::post('/forgot-password', [ForgotPasswordController::class, 'store'])
        ->middleware('throttle:12,1')
        ->name('password.email');
    Route::get('/reset-password/{token}', [ResetPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [ResetPasswordController::class, 'store'])->name('password.update');
});

Route::post('/logout', [LoginController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

/*
|--------------------------------------------------------------------------
| Customer account (profile, addresses, wishlist, reviews, checkout, orders)
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function () {
    Route::get('/account', AccountController::class)->name('account.index');
    Route::get('/account/profile', [ProfileController::class, 'edit'])->name('account.profile.edit');
    Route::put('/account/profile', [ProfileController::class, 'update'])->name('account.profile.update');
    Route::get('/account/addresses', [AddressController::class, 'index'])->name('account.addresses.index');
    Route::post('/account/addresses', [AddressController::class, 'store'])->name('account.addresses.store');
    Route::get('/account/addresses/{address}/edit', [AddressController::class, 'edit'])->name('account.addresses.edit');
    Route::put('/account/addresses/{address}', [AddressController::class, 'update'])->name('account.addresses.update');
    Route::delete('/account/addresses/{address}', [AddressController::class, 'destroy'])->name('account.addresses.destroy');
    Route::patch('/account/addresses/{address}/default', [AddressController::class, 'makeDefault'])->name('account.addresses.default');
    Route::get('/account/documents', [AccountIdentityDocumentController::class, 'index'])->name('account.documents.index');
    Route::post('/account/documents', [AccountIdentityDocumentController::class, 'store'])->name('account.documents.store');
    Route::delete('/account/documents/{document}', [AccountIdentityDocumentController::class, 'destroy'])->name('account.documents.destroy');
    Route::get('/account/reductions', [AccountDiscountCodeController::class, 'index'])->name('account.discounts.index');
    Route::get('/account/messages', [AccountConversationController::class, 'index'])->name('account.conversations.index');
    Route::get('/account/messages/{conversation}', [AccountConversationController::class, 'show'])->name('account.conversations.show');
    Route::post('/account/messages/{conversation}/reply', [AccountConversationController::class, 'reply'])
        ->middleware('throttle:10,1')
        ->name('account.conversations.reply');
    Route::get('/account/wishlist', [WishlistController::class, 'index'])->name('account.wishlist.index');

    Route::post('/wishlist', [WishlistController::class, 'store'])->name('wishlist.store');
    Route::delete('/wishlist/{product:slug}', [WishlistController::class, 'destroy'])->name('wishlist.destroy');

    Route::post('/products/{product:slug}/reviews', [ReviewController::class, 'store'])->name('reviews.store');

    Route::get('/checkout', [CheckoutController::class, 'show'])->name('checkout.show');
    Route::get('/checkout/relay-points', [CheckoutController::class, 'relayPoints'])->name('checkout.relay-points');
    Route::get('/checkout/postal-codes', [CheckoutController::class, 'postalCodeSearch'])->name('checkout.postal-codes');
    Route::post('/checkout/addresses', [CheckoutController::class, 'storeAddress'])->name('checkout.addresses.store');
    Route::post('/checkout/discount-code', [CheckoutController::class, 'applyDiscountCode'])->name('checkout.discount-code.store');
    Route::delete('/checkout/discount-code', [CheckoutController::class, 'removeDiscountCode'])->name('checkout.discount-code.destroy');
    Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');
    Route::get('/checkout/stripe/success', [CheckoutController::class, 'stripeSuccess'])->name('checkout.stripe.success');
    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
    Route::get('/orders/{order}/invoice', [OrderController::class, 'invoice'])->name('orders.invoice');
});
