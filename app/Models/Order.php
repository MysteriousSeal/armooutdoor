<?php

namespace App\Models;

use App\Enums\DeliveryMethod;
use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;

#[Fillable([
    'number',
    'user_id',
    'status',
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
    'relay_point_id',
    'relay_snapshot',
    'subtotal_cents',
    'shipping_cents',
    'total_cents',
    'payment_method',
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
            'address_snapshot' => 'array',
            'billing_address_snapshot' => 'array',
            'carrier_snapshot' => 'array',
            'carrier_method' => DeliveryMethod::class,
            'relay_snapshot' => 'array',
            'subtotal_cents' => 'integer',
            'shipping_cents' => 'integer',
            'total_cents' => 'integer',
            'payment_method' => PaymentMethod::class,
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

    public function formattedShipping(): string
    {
        return format_euros($this->shipping_cents);
    }

    public function formattedTotal(): string
    {
        return format_euros($this->total_cents);
    }

    public function hasTracking(): bool
    {
        return filled($this->tracking_number);
    }

    public function trackingCarrierName(): string
    {
        return $this->trackingCarrier?->localizedName() ?: $this->carrierName();
    }

    public function invoiceIsAvailable(): bool
    {
        return ! in_array($this->status, ['placed', 'preparing'], true);
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
