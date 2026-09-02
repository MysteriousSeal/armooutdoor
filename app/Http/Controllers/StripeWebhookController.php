<?php

namespace App\Http\Controllers;

use App\Notifications\AdminStripeOrphanedPayment;
use App\Services\StripeCheckoutFinalizer;
use App\Support\AdminMail;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;

/**
 * Safety net alongside the success-redirect confirmation: finalizes the
 * order even if the customer closes the tab right after paying. Both
 * paths are idempotent on stripe_checkout_session_id.
 */
class StripeWebhookController extends Controller
{
    public function handle(Request $request, StripeCheckoutFinalizer $finalizer): Response
    {
        $secret = config('services.stripe.webhook_secret');

        try {
            $event = filled($secret)
                ? Webhook::constructEvent($request->getContent(), $request->header('Stripe-Signature', ''), $secret)
                : json_decode($request->getContent(), false, 512, JSON_THROW_ON_ERROR);
        } catch (SignatureVerificationException|\JsonException $exception) {
            Log::warning('Stripe webhook signature/payload invalid.', ['error' => $exception->getMessage()]);

            return response('Invalid payload', 400);
        }

        if (($event->type ?? null) !== 'checkout.session.completed') {
            return response('Ignored', 200);
        }

        $session = $event->data->object;

        if ($session->payment_status !== 'paid') {
            return response('Not paid', 200);
        }

        $metadata = $session->metadata;

        try {
            $finalizer->finalize(
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
            Log::error('Stripe webhook could not finalize order.', [
                'session_id' => $session->id,
                'error' => $exception->getMessage(),
            ]);

            // Money taken, no order to ship: the one failure that must not
            // wait for somebody to think of opening the orphans page.
            AdminMail::notify(
                new AdminStripeOrphanedPayment($session->id, $exception->getMessage()),
                'Could not email the orphaned-payment notice.',
                ['session_id' => $session->id],
            );

            return response('Could not finalize', 200);
        }

        return response('OK', 200);
    }
}
