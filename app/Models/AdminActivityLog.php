<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable([
    'user_id',
    'subject_type',
    'subject_id',
    'action',
    'description',
])]
class AdminActivityLog extends Model
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public static function record(string $action, ?Model $subject, string $description): void
    {
        static::query()->create([
            'user_id' => request()->user()?->id,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'action' => $action,
            'description' => $description,
        ]);
    }

    public function subjectLabel(): string
    {
        if ($this->subject_type === User::class) {
            return $this->subject?->is_admin ? 'Admin' : 'Customer';
        }

        return match ($this->subject_type) {
            Order::class => 'Order',
            PurchaseOrder::class => 'Purchase order',
            Product::class => 'Product',
            Discount::class => 'Discount',
            DiscountCode::class => 'Discount code',
            Supplier::class => 'Supplier',
            Marketplace::class => 'Marketplace',
            PackageType::class => 'Package type',
            Carrier::class => 'Carrier',
            Conversation::class => 'Conversation',
            default => 'Item',
        };
    }
}
