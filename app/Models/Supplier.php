<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'website', 'lead_time_days'])]
class Supplier extends Model
{
    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    protected function casts(): array
    {
        return [
            'lead_time_days' => 'integer',
        ];
    }
}
