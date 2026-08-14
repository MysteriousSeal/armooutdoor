<?php

namespace Database\Factories;

use App\Models\Address;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Address>
 */
class AddressFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'label' => 'Home',
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'line1' => fake()->streetAddress(),
            'line2' => null,
            'postal_code' => fake()->numerify('#####'),
            'city' => fake()->city(),
            'country' => 'FR',
            'phone' => '06'.fake()->numerify('########'),
            'is_default' => true,
        ];
    }
}
