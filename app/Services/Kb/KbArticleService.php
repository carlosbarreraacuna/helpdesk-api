<?php

namespace App\Services\Kb;

use App\Models\KbArticle;
use App\Models\KbArticleVersion;
use Illuminate\Support\Str;

class KbArticleService
{
    public function createArticle(array $data, int $authorId): KbArticle
    {
        $article = KbArticle::create([
            'title'          => $data['title'],
            'slug'           => Str::slug($data['title']) . '-' . Str::random(5),
            'category_id'    => $data['category_id'],
            'subcategory_id' => $data['subcategory_id'] ?? null,
            'author_id'      => $authorId,
            'status'         => 'draft',
            'current_version' => 1,
        ]);

        KbArticleVersion::create([
            'article_id'     => $article->id,
            'version_number' => 1,
            'title'          => $data['title'],
            'content'        => $data['content'],
            'change_summary' => $data['change_summary'] ?? 'Versión inicial',
            'editor_id'      => $authorId,
            'is_published'   => false,
        ]);

        if (!empty($data['tag_ids'])) {
            $article->tags()->sync($data['tag_ids']);
        }

        return $article->load(['category', 'subcategory', 'author', 'tags', 'versions']);
    }

    public function createVersion(KbArticle $article, array $data, int $editorId): KbArticleVersion
    {
        $nextVersion = $article->versions()->max('version_number') + 1;

        $version = KbArticleVersion::create([
            'article_id'     => $article->id,
            'version_number' => $nextVersion,
            'title'          => $data['title'] ?? $article->title,
            'content'        => $data['content'],
            'change_summary' => $data['change_summary'] ?? null,
            'editor_id'      => $editorId,
            'is_published'   => false,
        ]);

        $article->update([
            'title'           => $data['title'] ?? $article->title,
            'current_version' => $nextVersion,
        ]);

        if (isset($data['tag_ids'])) {
            $article->tags()->sync($data['tag_ids']);
        }

        return $version->load('editor');
    }

    public function publishVersion(KbArticle $article, int $versionId): KbArticle
    {
        $version = KbArticleVersion::where('article_id', $article->id)
            ->where('id', $versionId)
            ->firstOrFail();

        KbArticleVersion::where('article_id', $article->id)
            ->where('is_published', true)
            ->update(['is_published' => false]);

        $version->update(['is_published' => true]);

        $article->update([
            'status'          => 'published',
            'current_version' => $version->version_number,
            'published_at'    => now(),
        ]);

        return $article->fresh(['category', 'subcategory', 'author', 'tags', 'publishedVersion']);
    }

    public function archiveArticle(KbArticle $article): KbArticle
    {
        $article->update(['status' => 'archived']);
        return $article;
    }
}
