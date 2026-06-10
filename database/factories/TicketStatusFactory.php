<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class TicketStatusFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name'  => fake()->unique()->word(),
            'color' => fake()->hexColor(),
            'order' => fake()->numberBetween(1, 10),
        ];
    }
}
