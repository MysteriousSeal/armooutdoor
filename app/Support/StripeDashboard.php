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

    public static function paymentIntentUrl(string $paymentIntentId): ?string
    {
        $accountId = self::accountId();

        return $accountId ? "https://dashboard.stripe.com/{$accountId}/test/payments/{$paymentIntentId}" : null;
    }

    public static function customerUrl(string $customerId): ?string
    {
        $accountId = self::accountId();

        return $accountId ? "https://dashboard.stripe.com/{$accountId}/test/customers/{$customerId}" : null;
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
