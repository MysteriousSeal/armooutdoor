<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Product;
use App\Models\SiteVisit;
use Illuminate\Http\Request;

class VisitCounterService
{
    public function __construct(private GeoIpService $geoIp) {}

    public function record(Request $request): void
    {
        $geo = $this->geoIp->resolve($request);
        [$productId, $categoryId] = $this->resolveCatalogIds($request);

        SiteVisit::create([
            'path' => mb_substr('/'.ltrim($request->path(), '/'), 0, 2048),
            // The same first-party session cookie already used for the cart
            // and login — reused here to group page views into a visit,
            // never a separate tracking cookie of its own. Only carried once
            // the visitor accepted it via the cookie banner: declined or
            // undecided, the visit is recorded without it.
            'session_id' => $request->cookie('cookie_consent') === 'all' && $request->hasSession()
                ? $request->session()->getId()
                : null,
            'product_id' => $productId,
            'category_id' => $categoryId,
            'user_id' => $request->user()?->id,
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 512) ?: null,
            'referrer' => mb_substr((string) $request->header('referer'), 0, 2048) ?: null,
            'country' => $geo['country'],
            'city' => $geo['city'],
        ]);
    }

    /**
     * On a product page, the category comes along with it — so "top
     * categories" stays accurate even though shoppers spend most of their
     * time on product pages, not the category listing itself.
     *
     * @return array{0: ?int, 1: ?int}
     */
    private function resolveCatalogIds(Request $request): array
    {
        $product = $request->route('product');

        if ($product instanceof Product) {
            return [$product->id, $product->category_id];
        }

        $category = $request->route('category');

        if ($category instanceof Category) {
            return [null, $category->id];
        }

        return [null, null];
    }
}
