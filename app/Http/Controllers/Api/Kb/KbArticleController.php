<?php

namespace App\Http\Controllers\Api\Kb;

use App\Http\Controllers\Controller;
use App\Models\KbArticle;
use App\Models\KbArticleVersion;
use App\Models\KbArticleVote;
use App\Models\Ticket;
use App\Services\Kb\KbArticleService;
use App\Services\Kb\KbSearchService;
use Illuminate\Http\Request;

class KbArticleController extends Controller
{
    public function __construct(
        private KbArticleService $articleService,
        private KbSearchService  $searchService,
    ) {}

    public function index(Request $request)
    {
        $perPage  = $request->get('per_page', 15);
        $isPublic = $request->is('portal/*') || $request->is('api/portal/*');

        // Public portal: only published articles (via search service)
        if ($isPublic) {
            $filters = $request->only(['q', 'category_id', 'subcategory_id', 'tag_ids']);
            return response()->json($this->searchService->search($filters, $perPage));
        }

        // Internal panel: all statuses, filterable
        $query = KbArticle::with(['category', 'subcategory', 'author', 'tags', 'publishedVersion'])
            ->orderBy('created_at', 'desc');

        if ($request->filled('q')) {
            $term = '%' . $request->q . '%';
            $query->where(function ($q) use ($term) {
                $q->whereRaw('LOWER(title) LIKE LOWER(?)', [$term]);
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->filled('tag_ids')) {
            $tagIds = is_array($request->tag_ids) ? $request->tag_ids : explode(',', $request->tag_ids);
            $query->whereHas('tags', fn($tq) => $tq->whereIn('kb_tags.id', $tagIds));
        }

        return response()->json($query->paginate($perPage));
    }

    public function suggest(Request $request)
    {
        $q = $request->get('q', '');
        $suggestions = $this->searchService->suggest($q);
        return response()->json($suggestions);
    }

    public function show($id)
    {
        $article = KbArticle::with([
            'category',
            'subcategory',
            'author',
            'tags',
            'publishedVersion.editor',
            'versions.editor',
        ])->findOrFail($id);

        $article->increment('views_count');

        $userVote = null;
        if ($user = request()->user()) {
            $vote = KbArticleVote::where('article_id', $id)->where('user_id', $user->id)->first();
            $userVote = $vote?->vote;
        }

        return response()->json(array_merge($article->toArray(), ['user_vote' => $userVote]));
    }

    public function store(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'title'          => 'required|string|max:255',
            'content'        => 'required|string',
            'category_id'    => 'required|exists:kb_categories,id',
            'subcategory_id' => 'nullable|exists:kb_subcategories,id',
            'tag_ids'        => 'nullable|array',
            'tag_ids.*'      => 'exists:kb_tags,id',
            'change_summary' => 'nullable|string|max:500',
        ]);

        $article = $this->articleService->createArticle($validated, $user->id);

        return response()->json($article, 201);
    }

    public function storeVersion(Request $request, $id)
    {
        $user = $request->user();
        $article = KbArticle::findOrFail($id);

        $validated = $request->validate([
            'title'          => 'nullable|string|max:255',
            'content'        => 'required|string',
            'change_summary' => 'nullable|string|max:500',
            'tag_ids'        => 'nullable|array',
            'tag_ids.*'      => 'exists:kb_tags,id',
        ]);

        $version = $this->articleService->createVersion($article, $validated, $user->id);

        return response()->json($version, 201);
    }

    public function versions($id)
    {
        $article = KbArticle::findOrFail($id);
        $versions = $article->versions()->with('editor')->get();
        return response()->json($versions);
    }

    public function showVersion($articleId, $versionId)
    {
        $version = KbArticleVersion::where('article_id', $articleId)
            ->where('id', $versionId)
            ->with('editor')
            ->firstOrFail();

        return response()->json($version);
    }

    public function publishVersion(Request $request, $articleId, $versionId)
    {
        $article = KbArticle::findOrFail($articleId);
        $updated = $this->articleService->publishVersion($article, $versionId);
        return response()->json($updated);
    }

    public function updateStatus(Request $request, $id)
    {
        $article = KbArticle::findOrFail($id);

        $validated = $request->validate([
            'status' => 'required|in:draft,published,archived',
        ]);

        if ($validated['status'] === 'archived') {
            $this->articleService->archiveArticle($article);
        } elseif ($validated['status'] === 'published') {
            $publishedVersion = $article->versions()->where('is_published', true)->first();
            if (!$publishedVersion) {
                $latest = $article->versions()->orderBy('version_number', 'desc')->first();
                if ($latest) {
                    $this->articleService->publishVersion($article, $latest->id);
                }
            } else {
                $article->update(['status' => 'published', 'published_at' => now()]);
            }
        } else {
            $article->update(['status' => $validated['status']]);
        }

        return response()->json($article->fresh());
    }

    public function destroy($id)
    {
        $article = KbArticle::findOrFail($id);
        $article->delete();
        return response()->json(['message' => 'Artículo eliminado']);
    }

    public function vote(Request $request, $id)
    {
        $user = $request->user();
        $article = KbArticle::findOrFail($id);

        $validated = $request->validate([
            'vote' => 'required|in:1,-1',
        ]);

        $existing = KbArticleVote::where('article_id', $id)->where('user_id', $user->id)->first();

        if ($existing) {
            $oldVote = $existing->vote;
            $existing->update(['vote' => $validated['vote']]);

            if ($oldVote === 1 && $validated['vote'] === -1) {
                $article->decrement('useful_count');
                $article->increment('not_useful_count');
            } elseif ($oldVote === -1 && $validated['vote'] === 1) {
                $article->decrement('not_useful_count');
                $article->increment('useful_count');
            }
        } else {
            KbArticleVote::create([
                'article_id' => $id,
                'user_id'    => $user->id,
                'vote'       => $validated['vote'],
            ]);

            if ($validated['vote'] === 1) {
                $article->increment('useful_count');
            } else {
                $article->increment('not_useful_count');
            }
        }

        return response()->json([
            'useful_count'     => $article->fresh()->useful_count,
            'not_useful_count' => $article->fresh()->not_useful_count,
            'user_vote'        => (int) $validated['vote'],
        ]);
    }

    public function userVote($id, Request $request)
    {
        $user = $request->user();
        $vote = KbArticleVote::where('article_id', $id)->where('user_id', $user->id)->first();
        return response()->json(['vote' => $vote?->vote]);
    }

    // Ticket integration
    public function ticketArticles($ticketId)
    {
        $ticket = Ticket::findOrFail($ticketId);
        $articles = $ticket->kbArticles ?? [];

        $linked = KbArticle::with(['category', 'publishedVersion'])
            ->whereHas('linkedTickets', function ($q) use ($ticketId) {
                $q->where('tickets.id', $ticketId);
            })->get();

        return response()->json($linked);
    }

    public function linkToTicket(Request $request, $ticketId)
    {
        $user = $request->user();
        Ticket::findOrFail($ticketId);

        $validated = $request->validate([
            'article_id' => 'required|exists:kb_articles,id',
        ]);

        \DB::table('ticket_kb_articles')->updateOrInsert(
            ['ticket_id' => $ticketId, 'article_id' => $validated['article_id']],
            ['linked_by' => $user->id, 'linked_at' => now()]
        );

        return response()->json(['message' => 'Artículo vinculado al ticket']);
    }

    public function unlinkFromTicket($ticketId, $articleId)
    {
        \DB::table('ticket_kb_articles')
            ->where('ticket_id', $ticketId)
            ->where('article_id', $articleId)
            ->delete();

        return response()->json(['message' => 'Artículo desvinculado del ticket']);
    }
}
