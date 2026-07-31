<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class DiscountFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('DISC-####')),
            'name' => fake()->words(3, true),
            'type' => 'fixed',
            'value' => 5000,
            'usage_count' => 0,
            'is_active' => true,
        ];
    }
}
