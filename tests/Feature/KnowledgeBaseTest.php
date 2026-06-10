<?php

namespace Tests\Feature;

use App\Models\KbArticle;
use App\Models\KbArticleVersion;
use App\Models\KbCategory;
use Tests\TestCase;

class KnowledgeBaseTest extends TestCase
{
    private function makeCategory(): KbCategory
    {
        return KbCategory::factory()->create();
    }

    public function test_public_portal_can_list_published_articles(): void
    {
        $category = $this->makeCategory();
        $author   = $this->createUser();

        $article = KbArticle::factory()->create([
            'category_id' => $category->id,
            'author_id'   => $author->id,
            'status'      => 'published',
        ]);

        KbArticleVersion::factory()->create([
            'article_id'   => $article->id,
            'editor_id'    => $author->id,
            'is_published' => true,
        ]);

        $this->getJson('/api/portal/kb/articles')
            ->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_agent_can_create_draft_article(): void
    {
        $agent    = $this->createUser('agente');
        $category = $this->makeCategory();
        $this->grantPermission($agent, 'kb.create');

        $this->actingAs($agent, 'sanctum')
            ->postJson('/api/kb/articles', [
                'title'       => 'Cómo reiniciar el servicio de red',
                'content'     => 'Paso 1: Abrir terminal...',
                'category_id' => $category->id,
            ])
            ->assertCreated();
    }

    public function test_user_without_kb_create_cannot_create_article(): void
    {
        $user = $this->createUser('usuario');

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/kb/articles', [
                'title'   => 'Test',
                'content' => 'Content',
            ])
            ->assertForbidden();
    }

    public function test_supervisor_can_create_new_version(): void
    {
        $supervisor = $this->createUser('supervisor');
        $category   = $this->makeCategory();
        $this->grantPermission($supervisor, 'kb.edit');

        $article = KbArticle::factory()->create([
            'category_id' => $category->id,
            'author_id'   => $supervisor->id,
            'status'      => 'draft',
        ]);

        $this->actingAs($supervisor, 'sanctum')
            ->postJson("/api/kb/articles/{$article->id}/versions", [
                'title'   => 'Versión actualizada',
                'content' => 'Contenido actualizado de la versión 2.',
            ])
            ->assertCreated();

        $this->assertDatabaseHas('kb_article_versions', [
            'article_id' => $article->id,
        ]);
    }

    public function test_admin_can_publish_version(): void
    {
        $admin    = $this->createAdmin();
        $category = $this->makeCategory();
        $this->grantPermission($admin, 'kb.publish');

        $article = KbArticle::factory()->create([
            'category_id' => $category->id,
            'author_id'   => $admin->id,
            'status'      => 'draft',
        ]);

        $version = KbArticleVersion::factory()->create([
            'article_id'   => $article->id,
            'editor_id'    => $admin->id,
            'is_published' => false,
        ]);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/kb/articles/{$article->id}/versions/{$version->id}/publish")
            ->assertOk();

        $this->assertDatabaseHas('kb_article_versions', [
            'id'          => $version->id,
            'is_published' => true,
        ]);
    }

    public function test_authenticated_user_can_vote_on_article(): void
    {
        $user     = $this->createUser();
        $category = $this->makeCategory();

        $article = KbArticle::factory()->create([
            'category_id' => $category->id,
            'author_id'   => $user->id,
            'status'      => 'published',
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/kb/articles/{$article->id}/vote", [
                'vote' => 1,
            ])
            ->assertOk();

        $this->assertDatabaseHas('kb_article_votes', [
            'article_id' => $article->id,
            'user_id'    => $user->id,
            'vote'       => 1,
        ]);
    }
}
