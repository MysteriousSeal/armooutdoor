<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Stripe\StripeClient;

/**
 * Builds links into the Stripe dashboard. The account id must be in the URL
 * for it to resolve directly — without it, Stripe falls back to whichever
 * account is currently active in the browser and the link goes nowhere
 * useful.
 */
class StripeDashboard
{
    public static function accountId(): ?string
    {
        return Cache::remember('stripe_account_id', now()->addDay(), function (): ?string {
            try {
                $stripe = new StripeClient(config('services.stripe.secret'));

                return $stripe->accounts->retrieve()->id;
            } catch (\Throwable) {
                return null;
            }
        });
    }

    /**
     * Stripe keeps test data behind a /test/ segment. The segment was written
     * into every link, so the day the shop takes a real payment the admin
     * would click through to a page showing nothing — and read that as a
     * missing payment rather than a wrong link.
     *
     * The secret key decides, because it is the key that fetched the account
     * id in the first place: the mode of the link always matches the account
     * it points at. Anything unrecognised — no key, or a restricted rk_ key —
     * counts as test, the harmless way to be wrong.
     */
    public static function isLiveMode(): bool
    {
        return str_starts_with((string) config('services.stripe.secret'), 'sk_live_');
    }

    public static function paymentIntentUrl(string $paymentIntentId): ?string
    {
        return self::url('payments', $paymentIntentId);
    }

    public static function customerUrl(string $customerId): ?string
    {
        return self::url('customers', $customerId);
    }

    private static function url(string $section, string $id): ?string
    {
        $accountId = self::accountId();

        if ($accountId === null) {
            return null;
        }

        $mode = self::isLiveMode() ? '' : '/test';

        return "https://dashboard.stripe.com/{$accountId}{$mode}/{$section}/{$id}";
    }

    /**
     * Best-effort: the balance transaction that holds the fee isn't always
     * attached to the charge the instant payment succeeds, so this can
     * legitimately come back null right after checkout — callers should
     * retry later rather than treat that as a hard failure.
     */
    public static function fetchFeeCents(string $paymentIntentId): ?int
    {
        try {
            $stripe = new StripeClient(config('services.stripe.secret'));
            $paymentIntent = $stripe->paymentIntents->retrieve($paymentIntentId, [
                'expand' => ['latest_charge.balance_transaction'],
            ]);

            return $paymentIntent->latest_charge?->balance_transaction?->fee ?? null;
        } catch (\Throwable) {
            return null;
        }
    }
}
