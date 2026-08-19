<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\StripeCheckoutFinalizer;
use App\Support\StripeDashboard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Stripe\Checkout\Session;
use Stripe\StripeClient;

/**
 * Lists every Stripe Checkout Session created by the shop (successful,
 * failed/expired, or still pending) alongside its matching order, if any.
 * Also exposes finalize() to manually recover a paid session that never
 * turned into an order — see StripeCheckoutFinalizer — but that action is
 * intentionally not wired into this listing page (see orphaned-payments
 * Blade view); it stays available for a future, more guarded recovery flow.
 */
class StripePaymentController extends Controller
{
    public function index(Request $request): View
    {
        $stripe = new StripeClient(config('services.stripe.secret'));
        $accountId = StripeDashboard::accountId();

        $sessions = $stripe->checkout->sessions->all([
            'limit' => 100,
            'created' => ['gte' => now()->subDays(30)->timestamp],
            'expand' => ['data.payment_intent.latest_charge.balance_transaction'],
        ]);

        $ownSessions = collect($sessions->data)
            ->filter(fn ($session): bool => $session->mode === 'payment' && filled($session->metadata->user_id ?? null));

        $orders = Order::query()
            ->whereIn('stripe_checkout_session_id', $ownSessions->pluck('id'))
            ->get(['number', 'stripe_checkout_session_id', 'archived_at'])
            ->keyBy('stripe_checkout_session_id');

        $payments = $ownSessions
            ->map(function ($session) use ($orders): array {
                $paymentIntent = $session->payment_intent;
                $paymentIntentId = $paymentIntent?->id;
                $feeCents = $paymentIntent?->latest_charge?->balance_transaction?->fee ?? null;

                return [
                    'id' => $session->id,
                    'amount_cents' => $session->amount_total,
                    'amount' => number_format($session->amount_total / 100, 2, ',', ' ').' €',
                    'email' => $session->customer_details->email ?? $session->customer_email ?? '—',
                    'created_at' => Carbon::createFromTimestamp($session->created),
                    'payment_intent' => $paymentIntentId,
                    'customer' => is_string($session->customer) ? $session->customer : null,
                    'status' => $this->statusLabel($session),
                    'order_number' => $orders->get($session->id)?->number,
                    'order_archived' => $orders->get($session->id)?->isArchived() ?? false,
                    'payment_intent_url' => $paymentIntentId ? StripeDashboard::paymentIntentUrl($paymentIntentId) : null,
                    'customer_url' => is_string($session->customer) ? StripeDashboard::customerUrl($session->customer) : null,
                    'fee_cents' => $feeCents,
                    'fee' => $feeCents !== null ? number_format($feeCents / 100, 2, ',', ' ').' €' : null,
                ];
            })
            ->sortByDesc('created_at')
            ->values();

        $paid = $payments->where('status', 'paid');
        $orphanedCount = $paid->whereNull('order_number')->count();

        $kpis = [
            'revenue_cents' => $paid->sum('amount_cents'),
            'fee_cents' => $paid->sum('fee_cents'),
            'paid_count' => $paid->count(),
            'failed_count' => $payments->where('status', 'failed')->count(),
            'pending_count' => $payments->where('status', 'pending')->count(),
            'orphaned_count' => $orphanedCount,
            'success_rate' => $payments->isNotEmpty() ? round($paid->count() / $payments->count() * 100) : null,
        ];

        $tab = in_array($request->query('tab'), ['paid', 'pending', 'failed', 'orphaned'], true)
            ? (string) $request->query('tab')
            : 'all';

        $filteredPayments = match ($tab) {
            'paid' => $paid->values(),
            'pending' => $payments->where('status', 'pending')->values(),
            'failed' => $payments->where('status', 'failed')->values(),
            'orphaned' => $paid->whereNull('order_number')->values(),
            default => $payments,
        };

        return view('admin.stripe.orphaned-payments', [
            'payments' => $filteredPayments,
            'kpis' => $kpis,
            'tab' => $tab,
            'totalCount' => $payments->count(),
        ]);
    }

    /**
     * @param  Session  $session
     */
    private function statusLabel($session): string
    {
        if ($session->payment_status === 'paid' || $session->payment_status === 'no_payment_required') {
            return 'paid';
        }

        if ($session->status === 'expired') {
            return 'failed';
        }

        return 'pending';
    }

    public function finalize(string $sessionId, StripeCheckoutFinalizer $finalizer): RedirectResponse
    {
        $stripe = new StripeClient(config('services.stripe.secret'));
        $session = $stripe->checkout->sessions->retrieve($sessionId);

        if ($session->payment_status !== 'paid') {
            return back()->with('status', 'This session is not marked as paid by Stripe.');
        }

        $metadata = $session->metadata;

        try {
            $order = $finalizer->finalize(
                userId: (int) $metadata->user_id,
                addressId: (int) $metadata->address_id,
                billingAddressId: filled($metadata->billing_address_id) ? (int) $metadata->billing_address_id : null,
                carrierId: (int) $metadata->carrier_id,
                relayPointId: filled($metadata->relay_point_id) ? (int) $metadata->relay_point_id : null,
                discountCodeId: filled($metadata->discount_code_id) ? (int) $metadata->discount_code_id : null,
                stripeCheckoutSessionId: $session->id,
                stripePaymentIntentId: is_string($session->payment_intent) ? $session->payment_intent : null,
                stripeCustomerId: is_string($session->customer) ? $session->customer : null,
            );
        } catch (\RuntimeException $exception) {
            return back()->with('status', 'Could not create the order: '.$exception->getMessage().'.');
        }

        return redirect()
            ->route('admin.orders.show', $order)
            ->with('status', 'Order '.$order->number.' created from the orphaned payment.');
    }
}
