<?php

namespace App\Support;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The carts of visitors who are not signed in, read from their live sessions
 * for the admin's information. A guest cart only exists in its session, so
 * it is visible while the session lives (session.lifetime after the last
 * page load) and moves to the account when the visitor signs in.
 */
class GuestCarts
{
    private const LIMIT = 50;

    /** Whether sessions sit in the database, the only place they can be read from. */
    public static function readable(): bool
    {
        return config('session.driver') === 'database';
    }

    /**
     * Live guest carts with something in them, most recent first. Bots are
     * left out: a crawler that pressed « add to cart » is not a shopper.
     *
     * @return Collection<int, array{ip: ?string, browser: string, os: string, device: string, lastActivity: Carbon, lines: Collection<int, CartLine>, itemCount: int, totalCents: int}>
     */
    public static function active(): Collection
    {
        if (! self::readable()) {
            return collect();
        }

        $since = now()->subMinutes((int) config('session.lifetime'))->getTimestamp();

        $sessions = DB::table(config('session.table', 'sessions'))
            ->whereNull('user_id')
            ->where('last_activity', '>=', $since)
            ->orderByDesc('last_activity')
            ->select(['ip_address', 'user_agent', 'payload', 'last_activity'])
            // Streamed: with sessions kept for days, most rows are visitors
            // and crawlers with nothing in a cart, and the scan stops at LIMIT.
            ->cursor();

        $found = [];

        foreach ($sessions as $session) {
            $agent = UserAgentParser::parse($session->user_agent);

            if ($agent['is_bot']) {
                continue;
            }

            $items = self::cartItems((string) $session->payload);

            if ($items === []) {
                continue;
            }

            $found[] = ['session' => $session, 'agent' => $agent, 'items' => $items];

            if (count($found) >= self::LIMIT) {
                break;
            }
        }

        if ($found === []) {
            return collect();
        }

        $productIds = collect($found)->flatMap(fn (array $row): array => array_keys($row['items']))->unique();
        $variantIds = collect($found)->flatMap(fn (array $row): array => collect($row['items'])->flatMap(fn (array $variants): array => array_keys($variants))->all())
            ->filter()->unique();

        $products = Product::query()->with('discount')->whereIn('id', $productIds)->get()->keyBy('id');
        $variants = $variantIds->isEmpty() ? collect() : ProductVariant::query()->whereIn('id', $variantIds)->get()->keyBy('id');

        return collect($found)->map(function (array $row) use ($products, $variants): ?array {
            $lines = collect();

            foreach ($row['items'] as $productId => $byVariant) {
                $product = $products->get($productId);

                if ($product === null) {
                    continue;
                }

                foreach ($byVariant as $variantId => $quantity) {
                    $variant = $variantId === 0 ? null : $variants->get($variantId);

                    if ($variantId !== 0 && $variant === null) {
                        continue;
                    }

                    $lines->push(new CartLine($product, $quantity, $variant));
                }
            }

            if ($lines->isEmpty()) {
                return null;
            }

            return [
                'ip' => $row['session']->ip_address,
                'browser' => $row['agent']['browser'],
                'os' => $row['agent']['os'],
                'device' => $row['agent']['device'],
                'lastActivity' => Carbon::createFromTimestamp((int) $row['session']->last_activity),
                'lines' => $lines,
                'itemCount' => $lines->sum('quantity'),
                'totalCents' => $lines->sum(fn (CartLine $line): int => $line->lineCents()),
            ];
        })->filter()->values();
    }

    /**
     * The cart stored in one session payload, shaped like the storefront's
     * own guest cart: [productId => [variantId (0 = none) => quantity]].
     *
     * @return array<int, array<int, int>>
     */
    private static function cartItems(string $payload): array
    {
        $decoded = base64_decode($payload, true);

        if ($decoded === false) {
            return [];
        }

        // Laravel writes the payload as JSON or as PHP serialisation, per
        // session.serialization; serialised objects are never rebuilt.
        $data = config('session.serialization') === 'json'
            ? json_decode($decoded, true)
            : @unserialize($decoded, ['allowed_classes' => false]);
        $cart = is_array($data) ? ($data[Cart::SESSION_KEY] ?? null) : null;

        if (! is_array($cart)) {
            return [];
        }

        $items = [];

        foreach ($cart as $productId => $byVariant) {
            $productId = (int) $productId;

            if ($productId <= 0 || ! is_array($byVariant)) {
                continue;
            }

            foreach ($byVariant as $variantId => $quantity) {
                $variantId = (int) $variantId;
                $quantity = (int) $quantity;

                if ($variantId >= 0 && $quantity > 0) {
                    $items[$productId][$variantId] = $quantity;
                }
            }
        }

        return $items;
    }
}
