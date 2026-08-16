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
use App\Services\MondialRelayClient;
use App\Support\Cart;
use App\Support\CartLine;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CheckoutController extends Controller
{
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
        $sameBillingAddress = old('same_billing_address', true);
        $selectedBillingAddressId = old('billing_address_id', $selectedAddressId);
        $selectedCarrierId = old('carrier_id', $carriers->first()?->id);
        $selectedCarrier = $carriers->firstWhere('id', (int) $selectedCarrierId) ?? $carriers->first();

        $subtotalCents = $cart->totalCents();
        $weightGrams = $cart->totalWeightGrams();
        $shippingSetting = ShippingSetting::current();
        $carrierPricesCents = $carriers->mapWithKeys(
            fn (Carrier $carrier): array => [$carrier->id => $shippingSetting->effectivePriceCents($carrier, $subtotalCents, $weightGrams)],
        );

        $discountCode = $this->resolveAppliedDiscountCode($user);
        $discountCents = $discountCode ? $subtotalCents - $discountCode->apply($subtotalCents) : 0;

        return view('checkout.show', [
            'lines' => $cart->lines(),
            'subtotal' => $cart->formattedTotal(),
            'subtotalCents' => $subtotalCents,
            'carrierPricesCents' => $carrierPricesCents,
            'discountCode' => $discountCode,
            'discountCents' => $discountCents,
            'addresses' => $addresses,
            'homeCarriers' => $carriers->filter(fn (Carrier $carrier): bool => $carrier->method === DeliveryMethod::Home)->values(),
            'relayCarriers' => $carriers->filter(fn (Carrier $carrier): bool => $carrier->method === DeliveryMethod::Relay)->values(),
            'relayPoints' => new Collection(),
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

    public function relayPoints(Request $request): JsonResponse
    {
        $postalCode = trim((string) $request->query('postal_code'));
        $country = strtoupper((string) $request->query('country', 'FR'));

        $points = $this->relayPointsFor($postalCode, $country);

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

        $error = $discountCode->eligibilityError($request->user());

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

    private function discountCodeSectionResponse(Cart $cart, ?DiscountCode $discountCode, string $message, ?string $errorMessage = null): JsonResponse
    {
        $subtotalCents = $cart->totalCents();
        $discountCents = $discountCode ? $subtotalCents - $discountCode->apply($subtotalCents) : 0;

        $errors = new \Illuminate\Support\ViewErrorBag();

        if ($errorMessage !== null) {
            $errors->put('default', new \Illuminate\Support\MessageBag(['discount_code' => [$errorMessage]]));
        }

        $sectionHtml = view('partials.checkout-discount-code', [
            'discountCode' => $discountCode,
            'discountCents' => $discountCents,
        ])->with('errors', $errors)->render();

        return response()->json([
            'applied' => $discountCode !== null,
            'discountCents' => $discountCents,
            'discountLabel' => $discountCode ? __('store.order_discount_code', ['code' => $discountCode->code]) : null,
            'discountValueText' => '-'.format_euros($discountCents),
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

        try {
            $order = DB::transaction(function () use ($request, $cart, $address, $billingAddress, $carrier, $relayPoint): Order {
                $subtotal = $cart->totalCents();
                $shipping = ShippingSetting::current()->effectivePriceCents($carrier, $subtotal, $cart->totalWeightGrams());

                $discountCode = null;
                $discountCents = 0;
                $discountCodeId = session(self::DISCOUNT_CODE_SESSION_KEY);

                if ($discountCodeId !== null) {
                    $discountCode = DiscountCode::query()->lockForUpdate()->find($discountCodeId);

                    if ($discountCode !== null && $discountCode->eligibilityError($request->user()) === null) {
                        $discountCents = $subtotal - $discountCode->apply($subtotal);

                        if ($discountCode->hasLimitedQuantity()) {
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
                    'total_cents' => $subtotal - $discountCents + $shipping,
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
                        $product->reconcileQuantity();
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
     * Relay points near a postal code, sourced live from Mondial Relay and
     * cached locally (so the picked point still resolves to a stable local
     * row for order snapshots). Falls back to whatever is already cached
     * for that postal code if the live call is unavailable.
     *
     * @return Collection<int, RelayPoint>
     */
    private function relayPointsFor(?string $postalCode, string $country = 'FR'): Collection
    {
        if (blank($postalCode)) {
            return new Collection();
        }

        $results = app(MondialRelayClient::class)->searchByPostalCode($postalCode, $country);

        if ($results === []) {
            return RelayPoint::query()
                ->where('postal_code', $postalCode)
                ->orderBy('sort_order')
                ->get();
        }

        $slugs = [];

        foreach ($results as $index => $point) {
            $slug = 'mr-'.$point['num'];
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
