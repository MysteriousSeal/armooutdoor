<?php

namespace Database\Factories;

use App\Models\PurchaseOrder;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PurchaseOrder>
 */
class PurchaseOrderFactory extends Factory
{
    public function definition(): array
    {
        $supplier = Supplier::query()->first()
            ?? Supplier::query()->create(['name' => 'Factory Supplier', 'lead_time_days' => 5]);

        return [
            'number' => 'BC-'.now()->format('Ymd').'-'.strtoupper(Str::random(4)),
            'supplier_id' => $supplier->id,
            'supplier_name' => $supplier->name,
            'status' => 'draft',
        ];
    }

    public function sent(): static
    {
        return $this->state(fn (): array => ['status' => 'sent', 'sent_at' => now()]);
    }
}
