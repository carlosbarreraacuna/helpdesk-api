<?php

namespace Database\Factories;

use App\Models\AssetType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class AssetFactory extends Factory
{
    public function definition(): array
    {
        return [
            'asset_type_id' => AssetType::factory(),
            'name'          => fake()->words(3, true),
            'serial_number' => strtoupper(Str::random(3)) . '-' . fake()->unique()->numerify('######'),
            'status'        => 'available',
            'is_shared'     => false,
            'created_by'    => User::factory(),
        ];
    }
}
