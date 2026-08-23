<?php

namespace App\Support;

use App\Models\Carrier;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class Cart
{
    public const SESSION_KEY = 'cart';

    public const MAX_QUANTITY = 10;

    /**
     * @return Collection<int, CartLine>
     */
    public function lines(): Collection
    {
        $entries = $this->entries();

        if ($entries->isEmpty()) {
            return collect();
        }

        $products = Product::query()
            ->active()
            ->with('category.parent', 'discount', 'supplier')
            ->whereIn('id', $entries->pluck('product_id')->unique())
            ->get()
            ->keyBy('id');

        $variantIds = $entries->pluck('variant_id')->filter()->unique();

        $variants = $variantIds->isEmpty()
            ? collect()
            : ProductVariant::query()->whereIn('id', $variantIds)->get()->keyBy('id');

        return $entries
            ->map(function (array $entry) use ($products, $variants): ?CartLine {
                $product = $products->get($entry['product_id']);

                if ($product === null) {
                    return null;
                }

                if ($entry['variant_id'] !== null) {
                    $variant = $variants->get($entry['variant_id']);

                    if ($variant === null) {
                        return null;
                    }

                    return new CartLine($product, $entry['quantity'], $variant);
                }

                return new CartLine($product, $entry['quantity']);
            })
            ->filter()
            ->values();
    }

    public function quantity(): int
    {
        return $this->entries()->sum('quantity');
    }

    public function quantityOf(Product $product, ?ProductVariant $variant = null): int
    {
        $variantId = $variant?->id;

        return $this->entries()
            ->first(fn (array $entry): bool => $entry['product_id'] === $product->id && $entry['variant_id'] === $variantId)['quantity'] ?? 0;
    }

    public function totalCents(): int
    {
        return $this->lines()->sum(fn (CartLine $line): int => $line->lineCents());
    }

    /**
     * Total weight of the cart's products (variants don't carry a weight of
     * their own), used to resolve carrier price tiers.
     */
    public function totalWeightGrams(): int
    {
        return $this->lines()->sum(fn (CartLine $line): int => ($line->product->weight_grams ?? 0) * $line->quantity);
    }

    /**
     * False if any product currently in the cart restricts its carriers and
     * doesn't include this one.
     */
    public function allowsCarrier(Carrier $carrier): bool
    {
        return $this->lines()->every(fn (CartLine $line): bool => $line->product->isCarrierAllowed($carrier));
    }

    public function formattedTotal(): string
    {
        return format_euros($this->totalCents());
    }

    public function add(Product $product, int $quantity = 1, ?ProductVariant $variant = null): void
    {
        $this->update($product, $this->quantityOf($product, $variant) + $quantity, $variant);
    }

    public function update(Product $product, int $quantity, ?ProductVariant $variant = null): void
    {
        $maxAllowed = $variant?->maxPurchasable() ?? $product->maxPurchasable();
        $quantity = max(0, min(self::MAX_QUANTITY, $maxAllowed, $quantity));
        $variantId = $variant?->id;

        if (Auth::check()) {
            if ($quantity === 0) {
                CartItem::query()
                    ->where('user_id', Auth::id())
                    ->where('product_id', $product->id)
                    ->where('product_variant_id', $variantId)
                    ->delete();

                return;
            }

            // updateOrCreate already survives losing this race: it catches the
            // unique violation and re-reads the winner's row. That only works
            // once the database actually refuses the second row, which is what
            // the index on COALESCE(product_variant_id, 0) fixed.
            CartItem::query()->updateOrCreate(
                [
                    'user_id' => Auth::id(),
                    'product_id' => $product->id,
                    'product_variant_id' => $variantId,
                ],
                ['quantity' => $quantity],
            );

            return;
        }

        $items = $this->sessionItems();
        $variantKey = $variantId ?? 0;

        if ($quantity === 0) {
            unset($items[$product->id][$variantKey]);

            if (empty($items[$product->id])) {
                unset($items[$product->id]);
            }
        } else {
            $items[$product->id][$variantKey] = $quantity;
        }

        session()->put(self::SESSION_KEY, $items);
    }

    public function remove(Product $product, ?ProductVariant $variant = null): void
    {
        $this->update($product, 0, $variant);
    }

    public function clear(): void
    {
        if (Auth::check()) {
            CartItem::query()->where('user_id', Auth::id())->delete();

            return;
        }

        session()->forget(self::SESSION_KEY);
    }

    public function isEmpty(): bool
    {
        return $this->quantity() === 0;
    }

    public function claimFor(User $user): void
    {
        $items = $this->sessionItems();

        foreach ($items as $productId => $variants) {
            foreach ($variants as $variantKey => $quantity) {
                $item = CartItem::query()->firstOrNew([
                    'user_id' => $user->id,
                    'product_id' => $productId,
                    'product_variant_id' => $variantKey === 0 ? null : $variantKey,
                ]);

                $item->quantity = min(self::MAX_QUANTITY, (int) $item->quantity + (int) $quantity);
                $item->save();
            }
        }

        session()->forget(self::SESSION_KEY);
    }

    /**
     * @return Collection<int, array{product_id: int, variant_id: ?int, quantity: int}>
     */
    private function entries(): Collection
    {
        if (Auth::check()) {
            return CartItem::query()
                ->where('user_id', Auth::id())
                ->get(['product_id', 'product_variant_id', 'quantity'])
                ->map(fn (CartItem $item): array => [
                    'product_id' => (int) $item->product_id,
                    'variant_id' => $item->product_variant_id !== null ? (int) $item->product_variant_id : null,
                    'quantity' => (int) $item->quantity,
                ])
                ->values();
        }

        $entries = collect();

        foreach ($this->sessionItems() as $productId => $variants) {
            foreach ($variants as $variantKey => $quantity) {
                $entries->push([
                    'product_id' => $productId,
                    'variant_id' => $variantKey === 0 ? null : $variantKey,
                    'quantity' => $quantity,
                ]);
            }
        }

        return $entries;
    }

    /**
     * Guest cart storage, shaped as [productId => [variantId (0 = none) => quantity]].
     *
     * @return array<int, array<int, int>>
     */
    private function sessionItems(): array
    {
        $items = session(self::SESSION_KEY, []);

        if (! is_array($items)) {
            return [];
        }

        $normalized = [];

        foreach ($items as $productId => $variants) {
            $productId = (int) $productId;

            if ($productId <= 0 || ! is_array($variants)) {
                continue;
            }

            foreach ($variants as $variantKey => $quantity) {
                $variantKey = (int) $variantKey;
                $quantity = (int) $quantity;

                if ($variantKey < 0 || $quantity <= 0) {
                    continue;
                }

                $normalized[$productId][$variantKey] = min(self::MAX_QUANTITY, $quantity);
            }
        }

        return $normalized;
    }
}
