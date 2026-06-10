<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class AssetTypeFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);
        return [
            'name'         => Str::slug($name),
            'display_name' => ucwords($name),
            'icon'         => 'laptop',
            'is_system'    => false,
            'is_active'    => true,
        ];
    }
}
