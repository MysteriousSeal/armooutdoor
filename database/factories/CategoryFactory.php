<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'slug' => Str::slug($name),
            'name' => [
                'en' => Str::title($name),
                'fr' => Str::title($name),
            ],
            'description' => [
                'en' => fake()->sentence(),
                'fr' => fake()->sentence(),
            ],
            'sort_order' => 0,
        ];
    }
}
