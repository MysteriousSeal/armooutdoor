<?php

namespace App\Http\Controllers;

use App\Enums\DeliveryMethod;
use App\Http\Requests\PlaceOrderRequest;
use App\Http\Requests\StoreAddressRequest;
use App\Models\Address;
use App\Models\Carrier;
use App\Models\DiscountCode;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductVariant;
use App\Models\RelayPoint;
use App\Models\ShippingSetting;
use App\Models\User;
use App\Services\OrderStockAllocator;
use App\Services\SendcloudRelayClient;
use App\Services\StripeCheckoutFinalizer;
use App\Support\Cart;
use App\Support\CartLine;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Illuminate\View\View;
use Stripe\StripeClient;

class CheckoutController extends Controller
{
    public function __construct(private readonly OrderStockAllocator $allocator) {}

    private const DISCOUNT_CODE_SESSION_KEY = 'checkout.discount_code_id';

    public function show(Cart $cart): View|RedirectResponse
    {
        if ($cart->isEmpty()) {
            return redirect(localized_route('cart.show'))
                ->with('status', __('store.cart_empty'));
        }

        $user = request()->user();
        $addresses = $user->addresses()->get();
        $carriers = Carrier::query()->active()->get()->filter(fn (Carrier $carrier): bool => $cart->allowsCarrier($carrier))->values();
        $selectedAddressId = old('address_id', $addresses->firstWhere('is_default', true)?->id ?? $addresses->first()?->id);
        $selectedAddress = $addresses->firstWhere('id', (int) $selectedAddressId);
        $sameBillingAddress = old('same_billing_address', true);
        $selectedBillingAddressId = old('billing_address_id', $selectedAddressId);
        $selectedCarrierId = old('carrier_id', request()->query('carrier_id', $carriers->first()?->id));
        $selectedCarrier = $carriers->firstWhere('id', (int) $selectedCarrierId) ?? $carriers->first();

        $subtotalCents = $cart->totalCents();
        $weightGrams = $cart->totalWeightGrams();
        $shippingSetting = ShippingSetting::current();
        $carrierPricesCents = $carriers->mapWithKeys(
            fn (Carrier $carrier): array => [$carrier->id => $shippingSetting->effectivePriceCents($carrier, $subtotalCents, $weightGrams)],
        );

        $discountCode = $this->resolveAppliedDiscountCode($user);
        $discountCents = $discountCode ? $subtotalCents - $discountCode->apply($subtotalCents) : 0;

        // Which carriers the applied code waives delivery on, so the
        // client-side total can stay in step with the server's.
        $freeShippingCarrierIds = $this->freeShippingCarrierIds($cart, $discountCode);

        // No-JS fallback: the relay list is normally only ever populated by
        // the AJAX endpoint (fetching live on every page load would slow it
        // down for everyone), but a <noscript> form on the page can submit
        // a plain GET with this param to get a real, server-rendered list.
        $relayPostalCode = trim((string) request()->query('relay_postal_code', ''));
        $relayProvider = $selectedCarrier ? $this->providerForCarrier($selectedCarrier) : null;
        $relayPoints = ($relayPostalCode !== '' && $relayProvider !== null)
            ? $this->relayPointsFor($relayPostalCode, $selectedAddress?->country ?? 'FR', $relayProvider)
            : new Collection;

        return view('checkout.show', [
            'lines' => $cart->lines(),
            'subtotal' => $cart->formattedTotal(),
            'subtotalCents' => $subtotalCents,
            'carrierPricesCents' => $carrierPricesCents,
            'discountCode' => $discountCode,
            'discountCents' => $discountCents,
            'freeShippingCarrierIds' => $freeShippingCarrierIds,
            'addresses' => $addresses,
            'homeCarriers' => $carriers->filter(fn (Carrier $carrier): bool => $carrier->method === DeliveryMethod::Home)->values(),
            'relayCarriers' => $carriers->filter(fn (Carrier $carrier): bool => $carrier->method === DeliveryMethod::Relay)->values(),
            'relayPoints' => $relayPoints,
            'relayPostalCode' => $relayPostalCode !== '' ? $relayPostalCode : ($selectedAddress?->postal_code ?? ''),
            'selectedAddressId' => $selectedAddressId,
            'sameBillingAddress' => $sameBillingAddress,
            'selectedBillingAddressId' => $selectedBillingAddressId,
            'selectedCarrierId' => $selectedCarrier?->id,
            'selectedCarrierIsRelay' => $selectedCarrier?->isRelay() ?? false,
            'selectedRelayPointId' => old('relay_point_id'),
            'selectedPaymentMethod' => old('payment_method'),
            'paymentCanceled' => request()->boolean('payment_canceled'),
        ]);
    }

    private function providerForCarrier(Carrier $carrier): ?string
    {
        return match ($carrier->slug) {
            'mondial-relay' => 'mondial_relay',
            'relais-pickup' => 'chronopost',
            default => null,
        };
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

    public function relayPoints(Request $request): JsonResponse
    {
        $postalCode = trim((string) $request->query('postal_code'));
        $country = strtoupper((string) $request->query('country', 'FR'));
        $provider = $request->query('provider') === 'chronopost' ? 'chronopost' : 'mondial_relay';

        $points = $this->relayPointsFor($postalCode, $country, $provider);

        return response()->json([
            'points' => $points->map(fn (RelayPoint $point): array => [
                'id' => $point->id,
                'name' => $point->name,
                'line1' => $point->line1,
                'postal_code' => $point->postal_code,
                'city' => $point->city,
                'hours' => $point->hours,
                'search' => $point->searchBlob(),
            ])->values(),
        ]);
    }

    public function postalCodeSearch(Request $request): JsonResponse
    {
        $query = mb_strtolower(trim((string) $request->query('q')));

        if ($query === '') {
            return response()->json(['results' => []]);
        }

        $isNumeric = ctype_digit($query[0]);

        $matches = collect($this->postalCodes())
            ->filter(function (array $pair) use ($query, $isNumeric): bool {
                return $isNumeric
                    ? str_starts_with($pair[0], $query)
                    : str_starts_with(mb_strtolower($pair[1]), $query);
            })
            ->take(8)
            ->values();

        return response()->json(['results' => $matches]);
    }

    /**
     * @return array<int, array{0: string, 1: string}>
     */
    private function postalCodes(): array
    {
        return Cache::rememberForever('fr_postal_codes', function (): array {
            $path = resource_path('data/fr-postal-codes.json');

            if (! is_file($path)) {
                return [];
            }

            return json_decode(file_get_contents($path), true) ?? [];
        });
    }

    public function applyDiscountCode(Request $request, Cart $cart): RedirectResponse|JsonResponse
    {
        $code = strtoupper(trim((string) $request->input('code')));

        $discountCode = $code !== '' ? DiscountCode::query()->where('code', $code)->first() : null;

        if ($discountCode === null) {
            $error = __('store.discount_code_error_not_found');

            return $request->wantsJson()
                ? $this->discountCodeSectionResponse($cart, null, $error, $error)
                : back()->withErrors(['discount_code' => $error]);
        }

        $error = $discountCode->eligibilityError($request->user())
            ?? $discountCode->cartEligibilityError($cart->totalCents());

        if ($error !== null) {
            return $request->wantsJson()
                ? $this->discountCodeSectionResponse($cart, null, $error, $error)
                : back()->withErrors(['discount_code' => $error]);
        }

        session()->put(self::DISCOUNT_CODE_SESSION_KEY, $discountCode->id);
        $message = __('store.discount_code_applied', ['code' => $discountCode->code]);

        if ($request->wantsJson()) {
            return $this->discountCodeSectionResponse($cart, $discountCode, $message);
        }

        return redirect(localized_route('checkout.show'))->with('status', $message);
    }

    public function removeDiscountCode(Request $request, Cart $cart): RedirectResponse|JsonResponse
    {
        session()->forget(self::DISCOUNT_CODE_SESSION_KEY);
        $message = __('store.discount_code_removed');

        if ($request->wantsJson()) {
            return $this->discountCodeSectionResponse($cart, null, $message);
        }

        return redirect(localized_route('checkout.show'))->with('status', $message);
    }

    /**
     * Carriers whose delivery charge the applied code waives.
     *
     * @return array<int, int>
     */
    private function freeShippingCarrierIds(Cart $cart, ?DiscountCode $discountCode): array
    {
        if ($discountCode === null) {
            return [];
        }

        $subtotalCents = $cart->totalCents();
        $weightGrams = $cart->totalWeightGrams();
        $shippingSetting = ShippingSetting::current();

        return Carrier::query()->active()->get()
            ->filter(fn (Carrier $carrier): bool => $cart->allowsCarrier($carrier))
            ->filter(fn (Carrier $carrier): bool => $discountCode->shippingDiscountCents(
                $carrier,
                $shippingSetting->effectivePriceCents($carrier, $subtotalCents, $weightGrams),
                $subtotalCents,
            ) > 0)
            ->pluck('id')
            ->values()
            ->all();
    }

    private function discountCodeSectionResponse(Cart $cart, ?DiscountCode $discountCode, string $message, ?string $errorMessage = null): JsonResponse
    {
        $subtotalCents = $cart->totalCents();
        $discountCents = $discountCode ? $subtotalCents - $discountCode->apply($subtotalCents) : 0;

        $errors = new ViewErrorBag;

        if ($errorMessage !== null) {
            $errors->put('default', new MessageBag(['discount_code' => [$errorMessage]]));
        }

        $sectionHtml = view('partials.checkout-discount-code', [
            'discountCode' => $discountCode,
            'discountCents' => $discountCents,
        ])->with('errors', $errors)->render();

        return response()->json([
            'applied' => $discountCode !== null,
            'discountCents' => $discountCents,
            // The client recomputes the total too, so it needs to learn which
            // carriers this code waives — otherwise the shipping line would
            // only zero out after a reload.
            'freeShippingCarrierIds' => $this->freeShippingCarrierIds($cart, $discountCode),
            'discountLabel' => $discountCode ? __('store.order_discount_code', ['code' => $discountCode->code]) : null,
            'discountValueText' => $discountCode?->isFreeRelayShipping()
                ? __('store.discount_code_free_relay_label')
                : '-'.format_euros($discountCents),
            'sectionHtml' => $sectionHtml,
            'message' => $message,
        ], $errorMessage !== null ? 422 : 200);
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

        if ($request->validated('payment_method') === 'card') {
            return $this->startStripeCheckout($request, $cart, $address, $billingAddress, $carrier, $relayPoint);
        }

        try {
            $order = DB::transaction(function () use ($request, $cart, $address, $billingAddress, $carrier, $relayPoint): Order {
                $subtotal = $cart->totalCents();
                $shipping = ShippingSetting::current()->effectivePriceCents($carrier, $subtotal, $cart->totalWeightGrams());

                $discountCode = null;
                $discountCents = 0;
                $shippingDiscount = 0;
                $discountCodeId = session(self::DISCOUNT_CODE_SESSION_KEY);

                if ($discountCodeId !== null) {
                    $discountCode = DiscountCode::query()->lockForUpdate()->find($discountCodeId);

                    if ($discountCode !== null && $discountCode->eligibilityError($request->user()) === null) {
                        $discountCents = $subtotal - $discountCode->apply($subtotal);
                        $shippingDiscount = $discountCode->shippingDiscountCents($carrier, $shipping, $subtotal);

                        // Re-checked here, not just when the code was entered:
                        // the cart may have crossed the free-shipping
                        // threshold since. A code that ends up worth nothing
                        // is dropped rather than consumed, so it stays usable.
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
                    'discount_code_id' => $discountCode?->id,
                    'discount_code_snapshot' => $discountCode ? [
                        'code' => $discountCode->code,
                        'type' => $discountCode->type,
                        'value' => $discountCode->value,
                    ] : null,
                    'discount_cents' => $discountCents,
                    'shipping_discount_cents' => $shippingDiscount,
                    'total_cents' => max(0, $subtotal - $discountCents + $shipping - $shippingDiscount),
                    'payment_method' => $request->validated('payment_method'),
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
        } catch (\RuntimeException $exception) {
            if ($exception->getMessage() === 'stock') {
                return redirect(localized_route('cart.show'))
                    ->with('status', __('store.out_of_stock'));
            }

            throw $exception;
        }

        session()->forget(self::DISCOUNT_CODE_SESSION_KEY);

        return redirect(localized_route('orders.show', ['order' => $order->number]))
            ->with('status', __('store.order_placed'));
    }

    private function startStripeCheckout(
        Request $request,
        Cart $cart,
        Address $address,
        Address $billingAddress,
        Carrier $carrier,
        ?RelayPoint $relayPoint,
    ): RedirectResponse {
        $user = $request->user();
        $subtotal = $cart->totalCents();
        $shipping = ShippingSetting::current()->effectivePriceCents($carrier, $subtotal, $cart->totalWeightGrams());

        $discountCodeId = session(self::DISCOUNT_CODE_SESSION_KEY);
        $discountCode = $discountCodeId !== null ? DiscountCode::query()->find($discountCodeId) : null;
        $usable = $discountCode !== null && $discountCode->eligibilityError($user) === null;
        $discountCents = $usable ? $subtotal - $discountCode->apply($subtotal) : 0;
        $shippingDiscount = $usable ? $discountCode->shippingDiscountCents($carrier, $shipping, $subtotal) : 0;

        $totalCents = max(0, $subtotal - $discountCents + $shipping - $shippingDiscount);

        $stripe = new StripeClient(config('services.stripe.secret'));
        $existingCustomerId = $this->findStripeCustomerId($stripe, $user->email);

        $session = $stripe->checkout->sessions->create([
            'mode' => 'payment',
            ...($existingCustomerId !== null
                ? ['customer' => $existingCustomerId]
                : ['customer_email' => $user->email, 'customer_creation' => 'always']),
            'line_items' => [[
                'price_data' => [
                    'currency' => strtolower(config('shop.currency')),
                    'unit_amount' => $totalCents,
                    'product_data' => [
                        'name' => __('store.stripe_order_line_name', ['shop' => config('app.name')]),
                    ],
                ],
                'quantity' => 1,
            ]],
            'metadata' => [
                'user_id' => $user->id,
                'address_id' => $address->id,
                'billing_address_id' => $billingAddress->id,
                'carrier_id' => $carrier->id,
                'relay_point_id' => $relayPoint?->id,
                'discount_code_id' => $discountCode?->id,
            ],
            'success_url' => localized_route('checkout.stripe.success').'?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => localized_route('checkout.show', ['payment_canceled' => 1]),
        ]);

        return redirect()->away($session->url);
    }

    /**
     * Reuses an existing Stripe Customer for this email instead of letting
     * Checkout create a new one every time — otherwise a returning customer
     * ends up with a fresh Customer object on every order.
     */
    private function findStripeCustomerId(StripeClient $stripe, string $email): ?string
    {
        $customers = $stripe->customers->all(['email' => $email, 'limit' => 1]);

        return $customers->data[0]->id ?? null;
    }

    public function stripeSuccess(Request $request, StripeCheckoutFinalizer $finalizer): RedirectResponse
    {
        $sessionId = (string) $request->query('session_id', '');

        if ($sessionId === '') {
            return redirect(localized_route('checkout.show'));
        }

        $stripe = new StripeClient(config('services.stripe.secret'));
        $session = $stripe->checkout->sessions->retrieve($sessionId);

        if ($session->payment_status !== 'paid') {
            return redirect(localized_route('checkout.show'))
                ->with('status', __('store.payment_not_confirmed'));
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
            if ($exception->getMessage() === 'stock') {
                return redirect(localized_route('cart.show'))
                    ->with('status', __('store.out_of_stock'));
            }

            if ($exception->getMessage() === 'empty_cart') {
                return redirect(localized_route('orders.index'));
            }

            throw $exception;
        }

        session()->forget(self::DISCOUNT_CODE_SESSION_KEY);

        return redirect(localized_route('orders.show', ['order' => $order->number]))
            ->with('status', __('store.order_placed'));
    }

    private function resolveAppliedDiscountCode(User $user): ?DiscountCode
    {
        $id = session(self::DISCOUNT_CODE_SESSION_KEY);

        if ($id === null) {
            return null;
        }

        $discountCode = DiscountCode::query()->find($id);

        if ($discountCode === null || $discountCode->eligibilityError($user) !== null) {
            session()->forget(self::DISCOUNT_CODE_SESSION_KEY);

            return null;
        }

        return $discountCode;
    }

    /**
     * Relay points near a postal code, sourced live from the given
     * provider's network and cached locally (so the picked point still
     * resolves to a stable local row for order snapshots). Falls back to
     * whatever is already cached for that postal code if the live call is
     * unavailable.
     *
     * @return Collection<int, RelayPoint>
     */
    private function relayPointsFor(?string $postalCode, string $country = 'FR', string $provider = 'mondial_relay'): Collection
    {
        if (blank($postalCode)) {
            return new Collection;
        }

        $prefix = $provider === 'chronopost' ? 'cp-' : 'mr-';

        $results = app(SendcloudRelayClient::class)->searchByPostalCode($provider, $postalCode, $country);

        if ($results === []) {
            return RelayPoint::query()
                ->where('postal_code', $postalCode)
                ->where('slug', 'like', $prefix.'%')
                ->orderBy('sort_order')
                ->get();
        }

        $slugs = [];

        foreach ($results as $index => $point) {
            $slug = $prefix.$point['num'];
            $slugs[] = $slug;

            RelayPoint::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $point['name'],
                    'line1' => $point['line1'],
                    'postal_code' => $point['postal_code'],
                    'city' => $point['city'],
                    'country' => $point['country'] ?: $country,
                    'hours' => $point['hours'] ?? null,
                    'sort_order' => $index,
                ],
            );
        }

        $points = RelayPoint::query()
            ->whereIn('slug', $slugs)
            ->orderBy('sort_order')
            ->get();

        return $points
            ->sortBy(fn (RelayPoint $point): int => $point->postal_code === $postalCode ? 0 : 1)
            ->values();
    }
}
