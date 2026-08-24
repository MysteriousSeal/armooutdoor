<?php

namespace App\Providers;

use App\Models\Category;
use App\Models\CompanySetting;
use App\Models\Conversation;
use App\Models\Order;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Models\WishlistItem;
use App\Support\Cart;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
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
        $this->configureRateLimiting();

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
                'unreadConversationCount' => $this->unreadConversationCount(),
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
            fn ($view) => $view->with([
                'unreadConversationCount' => $this->unreadConversationCount(),
                'usableDiscountCount' => $this->usableDiscountCount(),
            ]),
        );

        // Nav badge counts. Gated on being an admin, not merely signed in:
        // these are shop-wide operational numbers, and the layout can be
        // rendered outside the admin middleware (an error page, say).
        View::composer('layouts.admin', function ($view): void {
            $isAdmin = Auth::guard('web')->check() && Auth::guard('web')->user()->isAdmin();

            $view->with([
                'unreadMessageCount' => $isAdmin
                    ? Conversation::query()->unreadForAdmin()->count()
                    : 0,
                'ordersAwaitingStartCount' => $isAdmin
                    ? Order::query()->awaitingStart()->count()
                    : 0,
                'unviewedCustomerCount' => $isAdmin
                    ? User::query()->unviewedByAdmin()->count()
                    : 0,
                'purchaseOrdersAwaitingReceiptCount' => $isAdmin
                    ? PurchaseOrder::query()->awaitingReceipt()->count()
                    : 0,
            ]);
        });
    }

    /**
     * Threads where the shop has replied since the customer last looked.
     * Resolved once per request and reused: the site header renders it on
     * every storefront page, and the account views ask for it again.
     */
    /**
     * L'API d'administration n'avait aucune limite de débit.
     *
     * Le groupe `api` n'étant pas défini, le `throttle` implicite de Laravel
     * ne s'appliquait pas : un jeton pouvait être deviné aussi vite que le
     * réseau le permettait. Le compteur porte sur le jeton présenté, ou sur
     * l'adresse quand il n'y en a pas — sans quoi mille jetons faux
     * compteraient pour mille compteurs distincts.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('admin-api', function (Request $request): Limit {
            return Limit::perMinute(120)->by($request->ip() ?? 'unknown');
        });
    }

    private function unreadConversationCount(): int
    {
        if (! Auth::check()) {
            return 0;
        }

        return once(fn (): int => Conversation::query()
            ->where('user_id', Auth::id())
            ->unreadForCustomer()
            ->count());
    }

    /**
     * Codes this customer can redeem right now. The account nav renders on
     * every account page, so the answer is resolved once per request and
     * shared with the hub card that shows the same figure.
     */
    private function usableDiscountCount(): int
    {
        if (! Auth::check()) {
            return 0;
        }

        return once(fn (): int => Auth::user()->usableDiscountCodes()->count());
    }
}
