<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class CashAccountFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('ACC-####')),
            'name' => fake()->words(2, true),
            'holder_name' => fake()->firstName(),
            'branch_id' => null,
            'balance' => 0,
            'account_type' => 'asset',
            'counts_as_cash' => true,
            'is_active' => true,
        ];
    }
}
