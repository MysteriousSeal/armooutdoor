<?php

namespace App\Providers;

use App\Models\Category;
use App\Models\CompanySetting;
use App\Models\Conversation;
use App\Models\WishlistItem;
use App\Support\Cart;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(Cart::class);

        // Resolved once per request and shared by every composer below, so
        // rendering a page full of product cards doesn't run one query per card.
        $this->app->singleton('wishlist.product-ids', function () {
            return Auth::check()
                ? WishlistItem::query()->where('user_id', Auth::id())->pluck('product_id')
                : collect();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        ResetPassword::createUrlUsing(function ($notifiable, string $token): string {
            return localized_route('password.reset', [
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ], 'fr');
        });

        View::composer('layouts.app', function ($view): void {
            $view->with([
                'navCategories' => Category::query()
                    ->whereNull('parent_id')
                    ->with(['children' => fn ($query) => $query->orderBy('sort_order')->withCount(['products as products_count' => fn ($q) => $q->active()])])
                    ->orderBy('sort_order')
                    ->get(),
                'cartCount' => app(Cart::class)->quantity(),
                'wishlistProductIds' => app('wishlist.product-ids'),
            ]);
        });

        // @extends child views (and anything they @include, like product cards)
        // render before the parent layout does, so the composer above alone
        // never reaches them — they need their own copy of the same data.
        View::composer(['partials.product-card', 'products.show'], function ($view): void {
            $view->with('wishlistProductIds', app('wishlist.product-ids'));
        });

        View::composer(
            ['legal.terms', 'legal.notice', 'legal.privacy', 'legal.withdrawal'],
            fn ($view) => $view->with('company', CompanySetting::current()),
        );

        View::composer(
            ['account.nav', 'account.index'],
            fn ($view) => $view->with('unreadConversationCount', Auth::check()
                ? Conversation::query()->where('user_id', Auth::id())->unreadForCustomer()->count()
                : 0),
        );

        View::composer(
            'layouts.admin',
            fn ($view) => $view->with('unreadMessageCount', Auth::guard('web')->check()
                ? Conversation::query()->unreadForAdmin()->count()
                : 0),
        );
    }
}
