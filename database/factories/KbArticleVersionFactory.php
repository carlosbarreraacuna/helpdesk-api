<?php

namespace Database\Factories;

use App\Models\KbArticle;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class KbArticleVersionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'article_id'     => KbArticle::factory(),
            'editor_id'      => User::factory(),
            'version_number' => 1,
            'title'          => fake()->sentence(6),
            'content'        => fake()->paragraphs(3, true),
            'is_published'   => false,
        ];
    }
}
