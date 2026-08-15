<?php

namespace App\Http\Controllers;

use App\Enums\DeliveryMethod;
use App\Http\Requests\PlaceOrderRequest;
use App\Http\Requests\StoreAddressRequest;
use App\Models\Address;
use App\Models\Carrier;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductVariant;
use App\Models\RelayPoint;
use App\Models\ShippingSetting;
use App\Support\Cart;
use App\Support\CartLine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    public function show(Cart $cart): View|RedirectResponse
    {
        if ($cart->isEmpty()) {
            return redirect(localized_route('cart.show'))
                ->with('status', __('store.cart_empty'));
        }

        $user = request()->user();
        $addresses = $user->addresses()->get();
        $carriers = Carrier::query()->active()->get();
        $selectedAddressId = old('address_id', $addresses->firstWhere('is_default', true)?->id ?? $addresses->first()?->id);
        $sameBillingAddress = old('same_billing_address', true);
        $selectedBillingAddressId = old('billing_address_id', $selectedAddressId);
        $selectedCarrierId = old('carrier_id', $carriers->first()?->id);
        $selectedCarrier = $carriers->firstWhere('id', (int) $selectedCarrierId) ?? $carriers->first();

        $subtotalCents = $cart->totalCents();
        $shippingSetting = ShippingSetting::current();
        $carrierPricesCents = $carriers->mapWithKeys(
            fn (Carrier $carrier): array => [$carrier->id => $shippingSetting->effectivePriceCents($carrier, $subtotalCents)],
        );

        return view('checkout.show', [
            'lines' => $cart->lines(),
            'subtotal' => $cart->formattedTotal(),
            'subtotalCents' => $subtotalCents,
            'carrierPricesCents' => $carrierPricesCents,
            'addresses' => $addresses,
            'homeCarriers' => $carriers->filter(fn (Carrier $carrier): bool => $carrier->method === DeliveryMethod::Home)->values(),
            'relayCarriers' => $carriers->filter(fn (Carrier $carrier): bool => $carrier->method === DeliveryMethod::Relay)->values(),
            'relayPoints' => RelayPoint::query()->orderBy('sort_order')->get(),
            'selectedAddressId' => $selectedAddressId,
            'sameBillingAddress' => $sameBillingAddress,
            'selectedBillingAddressId' => $selectedBillingAddressId,
            'selectedCarrierId' => $selectedCarrier?->id,
            'selectedCarrierIsRelay' => $selectedCarrier?->isRelay() ?? false,
            'selectedRelayPointId' => old('relay_point_id'),
            'selectedPaymentMethod' => old('payment_method'),
        ]);
    }

    public function storeAddress(StoreAddressRequest $request): RedirectResponse
    {
        $user = $request->user();
        $makeDefault = $user->addresses()->doesntExist() || $request->boolean('is_default');

        if ($makeDefault) {
            $user->addresses()->update(['is_default' => false]);
        }

        $address = $user->addresses()->create([
            ...$request->safe()->except('is_default'),
            'is_default' => $makeDefault,
        ]);

        return redirect(localized_route('checkout.show'))
            ->with('status', __('store.address_saved'))
            ->withInput(['address_id' => $address->id]);
    }

    public function store(PlaceOrderRequest $request, Cart $cart): RedirectResponse
    {
        if ($cart->isEmpty()) {
            return redirect(localized_route('cart.show'))
                ->with('status', __('store.cart_empty'));
        }

        $address = Address::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($request->integer('address_id'));

        $billingAddress = $request->boolean('same_billing_address')
            ? $address
            : Address::query()
                ->where('user_id', $request->user()->id)
                ->findOrFail($request->integer('billing_address_id'));

        $carrier = Carrier::query()
            ->where('active', true)
            ->findOrFail($request->integer('carrier_id'));

        $relayPoint = null;

        if ($carrier->isRelay()) {
            $relayPoint = RelayPoint::query()->findOrFail($request->integer('relay_point_id'));
        }

        try {
            $order = DB::transaction(function () use ($request, $cart, $address, $billingAddress, $carrier, $relayPoint): Order {
                $subtotal = $cart->totalCents();
                $shipping = ShippingSetting::current()->effectivePriceCents($carrier, $subtotal);

                $order = Order::query()->create([
                    'number' => Order::generateNumber(),
                    'user_id' => $request->user()->id,
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
                    'total_cents' => $subtotal + $shipping,
                    'payment_method' => $request->validated('payment_method'),
                ]);

                $cart->lines()->each(function (CartLine $line) use ($order): void {
                    $product = $line->product->newQuery()->lockForUpdate()->find($line->product->id);

                    if ($product === null) {
                        throw new \RuntimeException('stock');
                    }

                    if ($line->variant !== null) {
                        $variant = ProductVariant::query()->lockForUpdate()->find($line->variant->id);

                        if ($variant === null || $variant->quantity < $line->quantity) {
                            throw new \RuntimeException('stock');
                        }

                        $variant->decrement('quantity', $line->quantity);
                    } elseif ($product->quantity < $line->quantity) {
                        throw new \RuntimeException('stock');
                    } else {
                        $product->decrement('quantity', $line->quantity);
                    }

                    OrderItem::query()->create([
                        'order_id' => $order->id,
                        'product_id' => $line->product->id,
                        'product_variant_id' => $line->variant?->id,
                        'product_slug' => $line->product->slug,
                        'name' => $line->product->name,
                        'variant_label' => $line->variantLabel(),
                        'sku' => $line->variant?->sku ?? $line->product->sku,
                        'image' => $line->product->image,
                        'unit_price_cents' => $line->unitPriceCents(),
                        'quantity' => $line->quantity,
                        'line_cents' => $line->lineCents(),
                    ]);
                });

                $cart->clear();

                return $order;
            });
        } catch (\RuntimeException $exception) {
            if ($exception->getMessage() === 'stock') {
                return redirect(localized_route('cart.show'))
                    ->with('status', __('store.out_of_stock'));
            }

            throw $exception;
        }

        return redirect(localized_route('orders.show', ['order' => $order->number]))
            ->with('status', __('store.order_placed'));
    }
}
