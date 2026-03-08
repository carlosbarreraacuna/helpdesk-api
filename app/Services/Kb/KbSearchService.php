<?php

namespace App\Services\Kb;

use App\Models\KbArticle;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class KbSearchService
{
    public function search(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = KbArticle::with(['category', 'subcategory', 'author', 'tags', 'publishedVersion'])
            ->published();

        if (!empty($filters['q'])) {
            $term = '%' . $filters['q'] . '%';
            $query->where(function ($q) use ($term) {
                $q->whereRaw('LOWER(kb_articles.title) LIKE LOWER(?)', [$term])
                  ->orWhereHas('publishedVersion', function ($vq) use ($term) {
                      $vq->whereRaw('LOWER(content) LIKE LOWER(?)', [$term]);
                  });
            });
        }

        if (!empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        if (!empty($filters['subcategory_id'])) {
            $query->where('subcategory_id', $filters['subcategory_id']);
        }

        if (!empty($filters['tag_ids'])) {
            $tagIds = is_array($filters['tag_ids']) ? $filters['tag_ids'] : explode(',', $filters['tag_ids']);
            $query->whereHas('tags', function ($tq) use ($tagIds) {
                $tq->whereIn('kb_tags.id', $tagIds);
            });
        }

        return $query->orderBy('published_at', 'desc')->paginate($perPage);
    }

    public function suggest(string $query, int $limit = 5): array
    {
        if (strlen(trim($query)) < 3) {
            return [];
        }

        $term = '%' . $query . '%';

        return KbArticle::with(['category', 'publishedVersion'])
            ->published()
            ->where(function ($q) use ($term) {
                $q->whereRaw('LOWER(kb_articles.title) LIKE LOWER(?)', [$term])
                  ->orWhereHas('publishedVersion', function ($vq) use ($term) {
                      $vq->whereRaw('LOWER(content) LIKE LOWER(?)', [$term]);
                  });
            })
            ->orderBy('useful_count', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($article) {
                return [
                    'id'       => $article->id,
                    'title'    => $article->title,
                    'slug'     => $article->slug,
                    'category' => $article->category?->name,
                    'views'    => $article->views_count,
                    'useful'   => $article->useful_count,
                ];
            })
            ->toArray();
    }
}
