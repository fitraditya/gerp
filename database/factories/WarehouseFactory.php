<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class WarehouseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->lexify('WH???')),
            'name' => fake()->company().' Warehouse',
            'type' => 'branch',
            'address' => fake()->address(),
            'is_active' => true,
        ];
    }

    public function central(): static
    {
        return $this->state(fn () => ['type' => 'central', 'code' => 'CENTRAL']);
    }
}
