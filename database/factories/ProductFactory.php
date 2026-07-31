<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    public function definition(): array
    {
        $price = fake()->randomElement([5000, 10000, 15000, 20000, 25000]);

        return [
            'sku' => strtoupper(fake()->unique()->bothify('SKU-####')),
            'name' => "Rp{$price} Tier",
            'brand_id' => null,
            'price' => $price,
            'tier' => "Rp{$price} Tier",
            'is_active' => true,
        ];
    }
}
