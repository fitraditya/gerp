<?php

namespace Database\Factories;

use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

class BranchFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->lexify('BR???')),
            'name' => fake()->company().' Gerai',
            'type' => 'masjid',
            'pic_name' => fake()->firstName(),
            'warehouse_id' => Warehouse::factory(),
            'address' => fake()->address(),
            'is_active' => true,
        ];
    }
}
