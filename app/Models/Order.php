<?php

namespace App\Models;

use App\Enums\DeliveryMethod;
use App\Enums\PaymentMethod;
use App\Notifications\OrderConfirmed;
use App\Notifications\OrderPreparing;
use App\Support\DeferredMail;
use App\Support\ShippingEstimate;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;

#[Fillable([
    'number',
    'is_manual',
    'user_id',
    'status',
    'archived_at',
    'test_marked_at',
    'address_id',
    'address_snapshot',
    'billing_address_id',
    'billing_address_snapshot',
    'carrier_id',
    'carrier_method',
    'carrier_snapshot',
    'tracking_number',
    'tracking_carrier_id',
    'package_type_id',
    'package_type_name',
    'marketplace_id',
    'marketplace_name',
    'marketplace_note',
    'marketplace_commission_cents',
    'shipping_paid_cents',
    'relay_point_id',
    'relay_snapshot',
    'subtotal_cents',
    'shipping_cents',
    'shipping_discount_cents',
    'discount_code_id',
    'discount_code_snapshot',
    'discount_cents',
    'total_cents',
    'payment_method',
    'stripe_checkout_session_id',
    'stripe_payment_intent_id',
    'stripe_customer_id',
    'payment_fee_cents',
])]
class Order extends Model
{
    protected static function booted(): void
    {
        static::created(function (Order $order): void {
            $order->statusHistories()->create(['status' => $order->status]);
        });
    }

    protected function casts(): array
    {
        return [
            'is_manual' => 'boolean',
            'archived_at' => 'datetime',
            'test_marked_at' => 'datetime',
            'address_snapshot' => 'array',
            'billing_address_snapshot' => 'array',
            'carrier_snapshot' => 'array',
            'carrier_method' => DeliveryMethod::class,
            'relay_snapshot' => 'array',
            'subtotal_cents' => 'integer',
            'shipping_cents' => 'integer',
            'shipping_discount_cents' => 'integer',
            'discount_code_snapshot' => 'array',
            'discount_cents' => 'integer',
            'total_cents' => 'integer',
            'payment_method' => PaymentMethod::class,
            'payment_fee_cents' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'number';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * The estimated shipping date shown to the customer: orders placed
     * before 10am ship the same day, otherwise the next day — pushed past
     * the weekend when it lands on one. Backordered items add their own
     * order-date-plus-supplier-lead-time estimate to the mix; whichever
     * candidate date is latest is the one shown, since that's the item
     * holding up the whole shipment.
     */
    public function estimatedShippingDate(): Carbon
    {
        $candidates = collect([ShippingEstimate::standard($this->created_at)]);

        foreach ($this->items as $item) {
            if ($item->was_backordered && $item->supplier_lead_time_days !== null) {
                $candidates->push(ShippingEstimate::backordered($this->created_at, $item->supplier_lead_time_days));
            }
        }

        return $candidates->max();
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(ProductReview::class);
    }

    /**
     * Le transporteur choisi à la commande. Le nom affiché vient du snapshot
     * (carrierName()), qui survit à un renommage ; cette relation sert quand
     * on a besoin du transporteur lui-même, comme pour son suivi.
     */
    public function carrier(): BelongsTo
    {
        return $this->belongsTo(Carrier::class);
    }

    public function trackingCarrier(): BelongsTo
    {
        return $this->belongsTo(Carrier::class, 'tracking_carrier_id');
    }

    public function packageType(): BelongsTo
    {
        return $this->belongsTo(PackageType::class);
    }

    public function marketplace(): BelongsTo
    {
        return $this->belongsTo(Marketplace::class);
    }

    public function discountCode(): BelongsTo
    {
        return $this->belongsTo(DiscountCode::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->latest();
    }

    public function markStatus(string $status): void
    {
        $this->update(['status' => $status]);
        $this->statusHistories()->create(['status' => $status]);
    }

    /**
     * Addresses can only be edited before an order has shipped. Listed as an
     * allowlist (rather than excluding 'shipped') so future statuses like
     * 'delivered' or 'refunded' are locked out by default too.
     */
    public function addressIsEditable(): bool
    {
        return in_array($this->status, ['placed', 'preparing'], true);
    }

    public function formattedSubtotal(): string
    {
        return format_euros($this->subtotal_cents);
    }

    /**
     * The subtotal before any per-product discounts — subtotal_cents stays
     * the actual (discounted) figure the total is built from.
     */
    public function fullSubtotalCents(): int
    {
        return $this->items->sum(fn (OrderItem $item): int => $item->fullLineCents());
    }

    public function formattedFullSubtotal(): string
    {
        return format_euros($this->fullSubtotalCents());
    }

    public function discountedItems()
    {
        return $this->items->filter(fn (OrderItem $item): bool => $item->hasDiscount());
    }

    public function formattedShipping(): string
    {
        return format_euros($this->shipping_cents);
    }

    /**
     * What the customer actually paid for delivery. A discount code waives
     * the charge without touching shipping_cents, which keeps the real
     * carrier price so the invoice can still show what delivery would have
     * cost — so the two must be subtracted to get the charged amount.
     */
    public function chargedShippingCents(): int
    {
        return max(0, $this->shipping_cents - ($this->shipping_discount_cents ?? 0));
    }

    public function deliveryWasFree(): bool
    {
        return $this->chargedShippingCents() === 0;
    }

    /**
     * Free because a code waived it, rather than because the cart reached the
     * free-shipping threshold.
     */
    public function deliveryWasFreedByCode(): bool
    {
        return ($this->shipping_discount_cents ?? 0) > 0;
    }

    public function hasDiscountCode(): bool
    {
        return $this->discount_code_snapshot !== null;
    }

    public function discountCodeCode(): ?string
    {
        return $this->discount_code_snapshot['code'] ?? null;
    }

    public function formattedDiscountCents(): string
    {
        return format_euros($this->discount_cents);
    }

    /**
     * Read from the snapshot rather than the live code: the code may since
     * have been edited or deleted, and the order should keep showing what it
     * was actually placed with.
     */
    public function discountCodeWasFreeRelayShipping(): bool
    {
        return ($this->discount_code_snapshot['type'] ?? null) === DiscountCode::TYPE_FREE_RELAY_SHIPPING;
    }

    public function formattedTotal(): string
    {
        return format_euros($this->total_cents);
    }

    /**
     * What actually lands in the bank after the marketplace's cut, what
     * was paid out of pocket for shipping, and the card/PayPal processor's
     * fee are all taken off the order total.
     */
    public function perceivedTotalCents(): int
    {
        return $this->total_cents
            - ($this->marketplace_commission_cents ?? 0)
            - ($this->shipping_paid_cents ?? 0)
            - ($this->payment_fee_cents ?? 0);
    }

    public function formattedPerceivedTotal(): string
    {
        return format_euros($this->perceivedTotalCents());
    }

    /**
     * Vrai dès qu'un des trois coûts a été saisi, même à zéro.
     *
     * Zéro saisi et champ vide ne veulent pas dire la même chose : le premier
     * dit « vérifié, rien à déduire », le second « pas encore renseigné ». Les
     * confondre derrière un tiret cache le travail déjà fait.
     */
    public function hasRecordedCosts(): bool
    {
        return $this->marketplace_commission_cents !== null
            || $this->shipping_paid_cents !== null
            || $this->payment_fee_cents !== null;
    }

    /**
     * The marketplace commission, out-of-pocket shipping, and payment
     * processor fee combined — everything that's taken off the total
     * before it actually lands in the bank.
     */
    public function totalCostsCents(): int
    {
        return ($this->marketplace_commission_cents ?? 0)
            + ($this->shipping_paid_cents ?? 0)
            + ($this->payment_fee_cents ?? 0);
    }

    public function formattedTotalCosts(): string
    {
        return format_euros($this->totalCostsCents());
    }

    /**
     * The cost of the goods themselves, incl. VAT, from each line's product
     * average purchase cost — a margin figure, distinct from the deducted
     * costs above (commission, shipping, fees) which never touch the goods.
     *
     * Null the moment one line can't be priced — a deleted product, or one
     * never yet received on a purchase order — rather than silently summing
     * only the lines that can: a partial total reads as a complete one.
     *
     * @param  array<int, int>  $averageCostsByProductId  From
     *                                                    Product::averagePurchaseCostsInclVatCents(), keyed by product id.
     */
    public function productCostInclVatCents(array $averageCostsByProductId): ?int
    {
        // Aucune ligne n'est un cas différent d'un coût nul vérifié : il n'y
        // a simplement rien à chiffrer, pas une réponse « zéro » à afficher.
        if ($this->items->isEmpty()) {
            return null;
        }

        $total = 0;

        foreach ($this->items as $item) {
            if ($item->product_id === null || ! array_key_exists($item->product_id, $averageCostsByProductId)) {
                return null;
            }

            $total += $averageCostsByProductId[$item->product_id] * $item->quantity;
        }

        return $total;
    }

    /**
     * What actually landed, minus what the goods cost — the margin figure.
     * Null whenever the product cost is (see productCostInclVatCents()):
     * a profit computed over an unknown cost would be a guess dressed up
     * as a number.
     */
    public function profitInclVatCents(array $averageCostsByProductId): ?int
    {
        $productCostCents = $this->productCostInclVatCents($averageCostsByProductId);

        return $productCostCents === null ? null : $this->perceivedTotalCents() - $productCostCents;
    }

    /**
     * The payment processing fee as a share of the order total, e.g. "4.7%".
     */
    public function formattedPaymentFeePercentage(): ?string
    {
        if ($this->payment_fee_cents === null || $this->total_cents === 0) {
            return null;
        }

        $percentage = $this->payment_fee_cents / $this->total_cents * 100;

        return number_format($percentage, 1, ',', ' ').' %';
    }

    public function hasTracking(): bool
    {
        return filled($this->tracking_number);
    }

    public function hasBeenShipped(): bool
    {
        return $this->statusHistories->contains('status', 'shipped');
    }

    /**
     * Le lien de suivi du colis, ou null si le transporteur retenu n'a pas de
     * page connue. On suit le transporteur de suivi s'il est renseigné, sinon
     * celui de la livraison : c'est le même choix que trackingCarrierName().
     */
    public function trackingUrl(): ?string
    {
        return ($this->trackingCarrier ?? $this->carrier)
            ?->trackingUrlFor($this->tracking_number, $this->trackingPostcode());
    }

    /**
     * Le code postal du destinataire, que Mondial Relay demande en plus du
     * numéro. Celui de l'adresse de livraison d'abord — il est renseigné sur
     * toutes les commandes — et à défaut celui du point relais.
     */
    private function trackingPostcode(): ?string
    {
        $address = $this->address_snapshot['postal_code'] ?? null;

        return filled($address)
            ? (string) $address
            : ($this->relay_snapshot['postal_code'] ?? null);
    }

    public function trackingCarrierName(): string
    {
        return $this->trackingCarrier?->localizedName() ?: $this->carrierName();
    }

    /**
     * La mention à imprimer au bas de la facture.
     *
     * Elle est figée sur la commande à sa création, pour qu'une reformulation
     * ultérieure ne réécrive pas des factures déjà émises. Mais une commande
     * créée avant que la place de marché ait sa mention n'en a aucune : on
     * retombe alors sur celle de la plateforme, sans quoi sa facture partirait
     * sans la mention légale sur les frais encaissés par la plateforme.
     */
    public function invoiceNote(): ?string
    {
        return filled($this->marketplace_note)
            ? $this->marketplace_note
            : $this->marketplace?->note;
    }

    /** Customer-facing: an order isn't confirmed enough to bill until it ships. */
    public function invoiceIsAvailable(): bool
    {
        return ! in_array($this->status, ['placed', 'preparing', 'draft'], true);
    }

    /** Admin-facing: preparing already means the order is confirmed and paid. */
    public function adminInvoiceIsAvailable(): bool
    {
        return ! in_array($this->status, ['placed', 'draft'], true);
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    /**
     * Le statut tel qu'on l'écrit dans l'interface.
     *
     * La valeur stockée peut porter un underscore — `in_transit` — et
     * l'afficher brute donnait « In_transit ». Un seul endroit décide, pour
     * que la pastille, le filtre et l'historique disent tous la même chose.
     */
    public static function labelForStatus(?string $status): string
    {
        return ucfirst(str_replace('_', ' ', (string) $status));
    }

    public function statusLabel(): string
    {
        return self::labelForStatus($this->status);
    }

    /**
     * Orders nobody has begun preparing yet. Narrower than the "to prepare"
     * KPI on the orders list, which also counts orders already in progress.
     * Drafts fall out for free — a draft is never 'placed'.
     */
    public function scopeAwaitingStart(Builder $query): void
    {
        $query->whereNull('archived_at')->excludingTest()->where('status', 'placed');
    }

    /**
     * Orders placed for real. Every figure in the admin filters through this:
     * an order kept only as a record of testing must not move revenue, counts
     * or a customer's lifetime spend.
     */
    public function scopeExcludingTest(Builder $query): void
    {
        $query->whereNull('test_marked_at');
    }

    public function scopeOnlyTest(Builder $query): void
    {
        $query->whereNotNull('test_marked_at');
    }

    /**
     * A draft is not a record of anything that happened, so there is nothing
     * to file away. Drafts are deleted instead.
     */
    public function canBeArchived(): bool
    {
        return ! $this->isDraft();
    }

    public function canBeDeleted(): bool
    {
        return $this->isDraft();
    }

    /**
     * Drafts are already outside every figure, so marking one changes nothing
     * — and it would be the one way to move a draft out of the Drafts tab,
     * which is exactly what archiving is no longer allowed to do.
     */
    public function canBeMarkedAsTest(): bool
    {
        return ! $this->isDraft();
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    public function archive(): void
    {
        $this->update(['archived_at' => now()]);
    }

    public function unarchive(): void
    {
        $this->update(['archived_at' => null]);
    }

    public function isTest(): bool
    {
        return $this->test_marked_at !== null;
    }

    /**
     * Bookkeeping, not a rollback. Stock, discount-code quantities and the
     * invoice number this order consumed all stay as they are.
     */
    public function markAsTest(): void
    {
        $this->update(['test_marked_at' => now()]);
    }

    public function unmarkAsTest(): void
    {
        $this->update(['test_marked_at' => null]);
    }

    public function statusMessage(): string
    {
        $key = 'store.order_thanks_'.$this->status;

        return __(Lang::has($key) ? $key : 'store.order_thanks_placed');
    }

    public function hasSeparateBillingAddress(): bool
    {
        return $this->billing_address_snapshot !== null
            && $this->billing_address_snapshot !== $this->address_snapshot;
    }

    /**
     * Whether this order's customer gets emails from us at all.
     *
     * The whole policy, in one place: a marketplace order is the
     * marketplace's to talk about, and an external shadow account's
     * address was typed in by an admin — never verified by its owner, so
     * never written to.
     */
    public function wantsCustomerEmails(): bool
    {
        return $this->marketplace_id === null
            && $this->user !== null
            && ! $this->user->external;
    }

    /**
     * Mails the confirmation to whoever the policy says is owed one. Safe to
     * call from any path that just made the order real — the ineligible
     * simply pass through in silence.
     */
    public function sendConfirmationEmail(): void
    {
        if (! $this->wantsCustomerEmails()) {
            return;
        }

        DeferredMail::send('Could not email an order confirmation.', ['order_id' => $this->id],
            fn () => $this->user->notify(new OrderConfirmed($this)));
    }

    /**
     * Tells the customer their order moved into preparation — same audience
     * policy as the confirmation.
     */
    public function sendPreparingEmail(): void
    {
        if (! $this->wantsCustomerEmails()) {
            return;
        }

        DeferredMail::send('Could not email an order preparation notice.', ['order_id' => $this->id],
            fn () => $this->user->notify(new OrderPreparing($this)));
    }

    public function carrierName(): string
    {
        $name = $this->carrier_snapshot['name'] ?? [];

        if (! is_array($name)) {
            return (string) $name;
        }

        return $name['fr'] ?? '';
    }

    public static function generateNumber(): string
    {
        do {
            $number = 'AO-'.now()->format('Ymd').'-'.strtoupper(Str::random(4));
        } while (static::query()->where('number', $number)->exists());

        return $number;
    }
}
