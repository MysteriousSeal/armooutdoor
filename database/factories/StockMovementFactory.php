<?php

namespace Database\Factories;

use App\Enums\StockMovementReason;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockMovement>
 */
class StockMovementFactory extends Factory
{
    protected $model = StockMovement::class;

    public function definition(): array
    {
        $before = fake()->numberBetween(0, 40);
        $delta = fake()->numberBetween(-5, 5) ?: 1;

        return [
            'product_id' => Product::factory(),
            'product_variant_id' => null,
            'variant_label' => null,
            'reason' => fake()->randomElement(StockMovementReason::cases()),
            'delta' => $delta,
            'quantity_before' => $before,
            'quantity_after' => max(0, $before + $delta),
            'user_id' => null,
            'note' => null,
        ];
    }
}
