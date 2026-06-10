<?php

namespace Database\Factories;

use App\Models\KbCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class KbArticleFactory extends Factory
{
    public function definition(): array
    {
        $title = fake()->sentence(6);
        return [
            'title'            => $title,
            'slug'             => Str::slug($title) . '-' . Str::random(5),
            'category_id'      => KbCategory::factory(),
            'author_id'        => User::factory(),
            'status'           => 'draft',
            'current_version'  => 1,
            'views_count'      => 0,
            'useful_count'     => 0,
            'not_useful_count' => 0,
        ];
    }
}
