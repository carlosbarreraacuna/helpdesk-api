<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class KbCategoryFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);
        return [
            'name'        => $name,
            'slug'        => Str::slug($name),
            'description' => fake()->sentence(),
            'icon'        => 'folder',
            'color'       => '#3b82f6',
            'order_index' => fake()->numberBetween(1, 20),
            'is_active'   => true,
        ];
    }
}
