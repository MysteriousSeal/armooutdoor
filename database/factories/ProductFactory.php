<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return [
            'category_id' => Category::factory(),
            'slug' => Str::slug($name),
            'name' => [
                'en' => Str::title($name),
                'fr' => Str::title($name),
            ],
            'description' => [
                'en' => fake()->paragraph(),
                'fr' => fake()->paragraph(),
            ],
            'price_cents' => fake()->numberBetween(4500, 34900),
            'quantity' => 20,
            'image' => 'products/ridge-tent.jpg',
            'featured' => false,
            'sort_order' => 0,
        ];
    }
}
