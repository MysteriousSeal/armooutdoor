<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'code',
    'type',
    'value',
    'user_id',
    'quantity',
    'max_uses_per_customer',
    'ends_at',
])]
class DiscountCode extends Model
{
    public const TYPE_PERCENTAGE = 'percentage';

    public const TYPE_FIXED = 'fixed';

    /**
     * Waives the delivery charge when the order goes to a relay point.
     * Deliberately narrow: free home delivery, or free delivery on one named
     * carrier, are different features rather than variations of this one.
     */
    public const TYPE_FREE_RELAY_SHIPPING = 'free_relay_shipping';

    /**
     * Per-customer order counts already resolved for this instance.
     *
     * @var array<int, int>
     */
    protected array $customerUsageCounts = [];

    protected function casts(): array
    {
        return [
            'value' => 'integer',
            'quantity' => 'integer',
            'max_uses_per_customer' => 'integer',
            'ends_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function isFreeRelayShipping(): bool
    {
        return $this->type === self::TYPE_FREE_RELAY_SHIPPING;
    }

    public function isForCustomer(): bool
    {
        return $this->user_id !== null;
    }

    public function hasLimitedQuantity(): bool
    {
        return $this->quantity !== null;
    }

    /**
     * Applies to the whole cart total, unlike a product Discount.
     * `value` is a percentage (1-100) for `percentage`, or cents for `fixed`.
     */
    public function apply(int $totalCents): int
    {
        // A shipping code never touches the goods total.
        if ($this->isFreeRelayShipping()) {
            return $totalCents;
        }

        $discounted = $this->type === 'percentage'
            ? $totalCents - (int) round($totalCents * $this->value / 100)
            : $totalCents - $this->value;

        return max(0, $discounted);
    }

    /**
     * The short badge shown in the admin, so it stays English like the rest
     * of the back office. The storefront renders its own French copy from
     * store.discount_code_free_relay_label rather than calling this.
     */
    public function label(): string
    {
        return match ($this->type) {
            self::TYPE_PERCENTAGE => '-'.$this->value.'%',
            self::TYPE_FREE_RELAY_SHIPPING => 'Free relay delivery',
            default => '-'.format_euros($this->value),
        };
    }

    /**
     * The customer-facing counterpart to label(), which is English for the
     * back office. Same figures, the shop's language.
     */
    public function customerLabel(): string
    {
        return match ($this->type) {
            self::TYPE_PERCENTAGE => '-'.$this->value.'%',
            self::TYPE_FREE_RELAY_SHIPPING => __('store.discount_code_free_relay_label'),
            default => '-'.format_euros($this->value),
        };
    }

    /**
     * How long the customer has left, in French, or null when there is no
     * deadline. remainingLabel() is the admin's English version.
     */
    public function customerDeadlineLabel(): ?string
    {
        if ($this->ends_at === null) {
            return null;
        }

        // A cutoff in the middle of the day would otherwise read as "all day",
        // and the customer would meet a refusal they had no way to expect.
        $runsToEndOfDay = $this->ends_at->format('H:i') === '23:59';

        return __($runsToEndOfDay ? 'store.discount_code_valid_until' : 'store.discount_code_valid_until_time', [
            'date' => $this->ends_at->translatedFormat('j F Y'),
            'time' => $this->ends_at->format('H:i'),
        ]);
    }

    /**
     * Uses this customer still has, or null when unlimited.
     */
    public function remainingUsesFor(User $user): ?int
    {
        $limits = array_filter([
            $this->hasMaxUsesPerCustomer() ? $this->max_uses_per_customer - $this->customerUsageCount($user->id) : null,
            $this->hasLimitedQuantity() ? $this->quantity : null,
        ], fn (?int $value): bool => $value !== null);

        return $limits === [] ? null : max(0, min($limits));
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            self::TYPE_PERCENTAGE => 'Percentage off',
            self::TYPE_FREE_RELAY_SHIPPING => 'Free relay delivery',
            default => 'Fixed amount off',
        };
    }

    public function quantityLabel(): string
    {
        return $this->hasLimitedQuantity() ? (string) $this->quantity : 'Unlimited';
    }

    public function hasMaxUsesPerCustomer(): bool
    {
        return $this->max_uses_per_customer !== null;
    }

    public function maxUsesPerCustomerLabel(): string
    {
        return $this->hasMaxUsesPerCustomer() ? (string) $this->max_uses_per_customer : 'Unlimited';
    }

    public function isExpired(): bool
    {
        return $this->ends_at !== null && $this->ends_at->isPast();
    }

    public function formattedEndsAt(): ?string
    {
        return $this->ends_at?->format('d M Y · H:i');
    }

    /**
     * English relative time for the admin list. The shop locale is French,
     * so Carbon's default diffForHumans() would otherwise leak FR copy.
     */
    public function remainingLabel(): string
    {
        if ($this->ends_at === null) {
            return 'No deadline';
        }

        $relative = $this->ends_at->copy()->locale('en')->diffForHumans();

        return $this->isExpired() ? 'Expired '.$relative : 'Expires '.$relative;
    }

    /**
     * Out of uses — either the shared quantity pool is exhausted, or the
     * code is restricted to one customer who has already hit their own
     * per-customer cap (so no one else can ever use it either).
     */
    public function isSoldOut(): bool
    {
        if ($this->hasLimitedQuantity() && $this->quantity <= 0) {
            return true;
        }

        if ($this->isForCustomer() && $this->hasMaxUsesPerCustomer()
            && $this->customerUsageCount($this->user_id) >= $this->max_uses_per_customer) {
            return true;
        }

        return false;
    }

    public function status(): string
    {
        if ($this->isExpired()) {
            return 'expired';
        }

        if ($this->isSoldOut()) {
            return 'sold_out';
        }

        return 'active';
    }

    public function statusLabel(): string
    {
        return match ($this->status()) {
            'expired' => 'Expired',
            'sold_out' => 'No usage remaining',
            default => 'Active',
        };
    }

    public function customerUsageCount(int $userId): int
    {
        // Rendering one voucher asks for this up to three times — twice while
        // deciding eligibility, once more for the remaining-uses line — so the
        // answer is kept on the instance instead of being re-queried.
        return $this->customerUsageCounts[$userId] ??= $this->orders()->where('user_id', $userId)->count();
    }

    /**
     * Seed the count from a query that already aggregated it, so listing a
     * customer's codes costs one query rather than one per code.
     */
    public function rememberCustomerUsage(int $userId, int $count): void
    {
        $this->customerUsageCounts[$userId] = $count;
    }

    /**
     * Null when the code can be redeemed by this customer right now,
     * otherwise a translated reason to show them.
     */
    public function eligibilityError(?User $user): ?string
    {
        if ($this->isExpired()) {
            return __('store.discount_code_error_expired');
        }

        if ($this->isForCustomer() && (! $user || $user->id !== $this->user_id)) {
            return __('store.discount_code_error_not_for_you');
        }

        if ($this->isSoldOut()) {
            return __('store.discount_code_error_sold_out');
        }

        if ($this->hasMaxUsesPerCustomer() && $user && $this->customerUsageCount($user->id) >= $this->max_uses_per_customer) {
            return __('store.discount_code_error_limit_reached');
        }

        return null;
    }

    /**
     * Refusals that depend on the cart rather than the customer. Kept apart
     * from eligibilityError() so that method's callers don't all have to
     * acquire a cart they have no use for.
     *
     * A missing or home-delivery carrier is not an error: the code is
     * accepted and simply does not bite unless they pick a relay point.
     */
    public function cartEligibilityError(int $subtotalCents): ?string
    {
        if (! $this->isFreeRelayShipping()) {
            return null;
        }

        if ($this->relayDeliveryIsAlreadyFree($subtotalCents)) {
            return __('store.discount_code_error_relay_already_free');
        }

        return null;
    }

    /**
     * True only when every active relay carrier is already free at this
     * subtotal. Free shipping is configured per carrier, so if even one relay
     * option still charges, the code still has something to do.
     */
    public function relayDeliveryIsAlreadyFree(int $subtotalCents): bool
    {
        $relayCarriers = Carrier::query()->active()->get()->filter(
            fn (Carrier $carrier): bool => $carrier->isRelay(),
        );

        if ($relayCarriers->isEmpty()) {
            return false;
        }

        $shipping = ShippingSetting::current();

        return $relayCarriers->every(
            fn (Carrier $carrier): bool => $shipping->isFreeFor($carrier, $subtotalCents),
        );
    }

    /**
     * What this code takes off the shipping line for the chosen carrier.
     */
    public function shippingDiscountCents(?Carrier $carrier, int $shippingCents, int $subtotalCents): int
    {
        if (! $this->isFreeRelayShipping() || $carrier === null || ! $carrier->isRelay()) {
            return 0;
        }

        if ($this->relayDeliveryIsAlreadyFree($subtotalCents)) {
            return 0;
        }

        return $shippingCents;
    }
}
