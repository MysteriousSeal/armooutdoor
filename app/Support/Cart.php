<?php

namespace App\Support;

use App\Models\CartItem;
use App\Models\Product;
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
        $quantities = $this->quantities();

        if ($quantities === []) {
            return collect();
        }

        $products = Product::query()
            ->active()
            ->with('category.parent')
            ->whereIn('id', array_keys($quantities))
            ->get()
            ->keyBy('id');

        return collect($quantities)
            ->map(function (int $quantity, int $productId) use ($products): ?CartLine {
                $product = $products->get($productId);

                if ($product === null) {
                    return null;
                }

                return new CartLine($product, $quantity);
            })
            ->filter()
            ->values();
    }

    public function quantity(): int
    {
        return array_sum($this->quantities());
    }

    public function quantityOf(Product $product): int
    {
        return $this->quantities()[$product->id] ?? 0;
    }

    public function totalCents(): int
    {
        return $this->lines()->sum(fn (CartLine $line): int => $line->lineCents());
    }

    public function formattedTotal(): string
    {
        return format_euros($this->totalCents());
    }

    public function add(Product $product, int $quantity = 1): void
    {
        $this->update($product, ($this->quantities()[$product->id] ?? 0) + $quantity);
    }

    public function update(Product $product, int $quantity): void
    {
        $quantity = max(0, min(self::MAX_QUANTITY, $product->quantity, $quantity));

        if (Auth::check()) {
            if ($quantity === 0) {
                CartItem::query()
                    ->where('user_id', Auth::id())
                    ->where('product_id', $product->id)
                    ->delete();

                return;
            }

            CartItem::query()->updateOrCreate(
                [
                    'user_id' => Auth::id(),
                    'product_id' => $product->id,
                ],
                ['quantity' => $quantity],
            );

            return;
        }

        $items = $this->sessionItems();

        if ($quantity === 0) {
            unset($items[$product->id]);
        } else {
            $items[$product->id] = $quantity;
        }

        session()->put(self::SESSION_KEY, $items);
    }

    public function remove(Product $product): void
    {
        $this->update($product, 0);
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
        $sessionItems = $this->sessionItems();

        foreach ($sessionItems as $productId => $quantity) {
            $item = CartItem::query()->firstOrNew([
                'user_id' => $user->id,
                'product_id' => $productId,
            ]);

            $item->quantity = min(self::MAX_QUANTITY, (int) $item->quantity + (int) $quantity);
            $item->save();
        }

        session()->forget(self::SESSION_KEY);
    }

    /**
     * @return array<int, int>
     */
    private function quantities(): array
    {
        if (Auth::check()) {
            return CartItem::query()
                ->where('user_id', Auth::id())
                ->pluck('quantity', 'product_id')
                ->map(fn ($quantity): int => (int) $quantity)
                ->all();
        }

        return $this->sessionItems();
    }

    /**
     * @return array<int, int>
     */
    private function sessionItems(): array
    {
        $items = session(self::SESSION_KEY, []);

        if (! is_array($items)) {
            return [];
        }

        $normalized = [];

        foreach ($items as $productId => $quantity) {
            $productId = (int) $productId;
            $quantity = (int) $quantity;

            if ($productId > 0 && $quantity > 0) {
                $normalized[$productId] = min(self::MAX_QUANTITY, $quantity);
            }
        }

        return $normalized;
    }
}
