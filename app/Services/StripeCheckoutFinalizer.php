<?php

namespace App\Services;

use App\Models\Address;
use App\Models\Carrier;
use App\Models\DiscountCode;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductVariant;
use App\Models\RelayPoint;
use App\Models\ShippingSetting;
use App\Support\Cart;
use App\Support\CartLine;
use App\Support\StripeDashboard;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Turns a paid Stripe Checkout Session into a real order — stock is only
 * touched once payment is confirmed, never at checkout submission time.
 * Called from both the success-redirect and the webhook, so it must not
 * depend on the current HTTP session (the webhook has none).
 */
class StripeCheckoutFinalizer
{
    public function __construct(private readonly OrderStockAllocator $allocator) {}

    /**
     * @throws \RuntimeException with message "stock" or "empty_cart"
     */
    public function finalize(
        int $userId,
        int $addressId,
        ?int $billingAddressId,
        int $carrierId,
        ?int $relayPointId,
        ?int $discountCodeId,
        string $stripeCheckoutSessionId,
        ?string $stripePaymentIntentId,
        ?string $stripeCustomerId = null,
    ): Order {
        $existing = Order::query()->where('stripe_checkout_session_id', $stripeCheckoutSessionId)->first();

        if ($existing !== null) {
            return $existing;
        }

        Auth::onceUsingId($userId);

        $cart = app(Cart::class);

        if ($cart->isEmpty()) {
            throw new \RuntimeException('empty_cart');
        }

        $address = Address::query()->where('user_id', $userId)->findOrFail($addressId);
        $billingAddress = $billingAddressId !== null
            ? Address::query()->where('user_id', $userId)->findOrFail($billingAddressId)
            : $address;
        $carrier = Carrier::query()->where('active', true)->findOrFail($carrierId);
        $relayPoint = $carrier->isRelay() ? RelayPoint::query()->findOrFail($relayPointId) : null;

        // Fetched once here, outside the transaction, so it's saved on the
        // order and the admin pages never need to call Stripe to show it.
        $paymentFeeCents = $stripePaymentIntentId !== null ? StripeDashboard::fetchFeeCents($stripePaymentIntentId) : null;

        return DB::transaction(function () use ($userId, $cart, $address, $billingAddress, $carrier, $relayPoint, $discountCodeId, $stripeCheckoutSessionId, $stripePaymentIntentId, $stripeCustomerId, $paymentFeeCents): Order {
            $subtotal = $cart->totalCents();
            $shipping = ShippingSetting::current()->effectivePriceCents($carrier, $subtotal, $cart->totalWeightGrams());

            $discountCode = null;
            $discountCents = 0;
            $shippingDiscount = 0;

            if ($discountCodeId !== null) {
                $discountCode = DiscountCode::query()->lockForUpdate()->find($discountCodeId);

                if ($discountCode !== null && $discountCode->eligibilityError(Auth::user()) === null) {
                    $discountCents = $subtotal - $discountCode->apply($subtotal);
                    $shippingDiscount = $discountCode->shippingDiscountCents($carrier, $shipping, $subtotal);

                    // Re-checked here, not just when the code was entered:
                    // the cart may have crossed the free-shipping threshold
                    // since. A code that ends up worth nothing is dropped
                    // rather than consumed, so it stays usable.
                    if ($discountCents === 0 && $shippingDiscount === 0 && $discountCode->isFreeRelayShipping()) {
                        $discountCode = null;
                    } elseif ($discountCode->hasLimitedQuantity()) {
                        $discountCode->decrement('quantity');
                    }
                } else {
                    $discountCode = null;
                }
            }

            $order = Order::query()->create([
                'number' => Order::generateNumber(),
                'user_id' => $userId,
                'status' => 'placed',
                'address_id' => $address->id,
                'address_snapshot' => $address->toSnapshot(),
                'billing_address_id' => $billingAddress->id,
                'billing_address_snapshot' => $billingAddress->toSnapshot(),
                'carrier_id' => $carrier->id,
                'carrier_method' => $carrier->method,
                'carrier_snapshot' => $carrier->toSnapshot(),
                'relay_point_id' => $relayPoint?->id,
                'relay_snapshot' => $relayPoint?->toSnapshot(),
                'subtotal_cents' => $subtotal,
                'shipping_cents' => $shipping,
                'discount_code_id' => $discountCode?->id,
                'discount_code_snapshot' => $discountCode ? [
                    'code' => $discountCode->code,
                    'type' => $discountCode->type,
                    'value' => $discountCode->value,
                ] : null,
                'shipping_discount_cents' => $shippingDiscount,
                'discount_cents' => $discountCents,
                // Must match what startStripeCheckout() charged, which also
                // subtracts the shipping waiver.
                'total_cents' => max(0, $subtotal - $discountCents + $shipping - $shippingDiscount),
                'payment_method' => 'card',
                'stripe_checkout_session_id' => $stripeCheckoutSessionId,
                'stripe_payment_intent_id' => $stripePaymentIntentId,
                'stripe_customer_id' => $stripeCustomerId,
                'payment_fee_cents' => $paymentFeeCents,
            ]);

            $allocator = $this->allocator;

            $cart->lines()->each(function (CartLine $line) use ($order, $allocator): void {
                $product = $line->product->newQuery()->lockForUpdate()->with('supplier')->find($line->product->id);

                if ($product === null) {
                    throw new \RuntimeException('stock');
                }

                $variant = null;

                if ($line->variant !== null) {
                    $variant = ProductVariant::query()->lockForUpdate()->find($line->variant->id);

                    if ($variant === null) {
                        throw new \RuntimeException('stock');
                    }
                }

                // A customer is put on backorder rather than turned away.
                $allocation = $allocator->allocate($product, $variant, $line->quantity, allowBackorder: true, order: $order);

                OrderItem::query()->create([
                    'order_id' => $order->id,
                    'product_id' => $line->product->id,
                    'product_variant_id' => $line->variant?->id,
                    'product_slug' => $line->product->slug,
                    'name' => $line->product->name,
                    'variant_label' => $line->variantLabel(),
                    'sku' => $line->variant?->sku ?? $line->product->sku,
                    'image' => $line->product->image,
                    'was_backordered' => $allocation->backordered,
                    'supplier_lead_time_days' => $allocation->supplierLeadTimeDays,
                    'unit_price_cents' => $line->unitPriceCents(),
                    'original_unit_price_cents' => $line->hasDiscount() ? $line->product->price_cents : null,
                    'discount_label' => $line->hasDiscount() ? $line->product->discount->label() : null,
                    'quantity' => $line->quantity,
                    'line_cents' => $line->lineCents(),
                ]);
            });

            $cart->clear();

            return $order;
        });
    }
}
