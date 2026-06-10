<?php

namespace App\Http\Controllers\Api\Widget;

use App\Http\Controllers\Controller;
use App\Models\WidgetChatSession;
use App\Services\Widget\WidgetChatService;
use Illuminate\Http\Request;

/**
 * @tags Widget / Chat
 */
class WidgetController extends Controller
{
    public function __construct(private WidgetChatService $chatService) {}

    public function session(Request $request)
    {
        $user    = $request->user();
        $context = $request->input('search_context');
        $session = $this->chatService->getOrCreateSession($user, $context);

        return response()->json($session->load([
            'messages' => fn($q) => $q->with('sender:id,name')->orderBy('created_at'),
            'assignedAgent:id,name',
            'ticket:id,ticket_number,status_id',
        ]));
    }

    public function recentTickets(Request $request)
    {
        $tickets = \App\Models\Ticket::where('created_by', $request->user()->id)
            ->with('status:id,name,color')
            ->select('id', 'ticket_number', 'description', 'status_id', 'created_at')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(fn($t) => [
                'id'             => $t->id,
                'ticket_number'  => $t->ticket_number,
                'summary'        => \Illuminate\Support\Str::limit(strip_tags($t->description), 60),
                'status'         => $t->status?->name,
                'status_color'   => $t->status?->color,
                'created_at'     => $t->created_at->toISOString(),
            ]);

        return response()->json($tickets);
    }

    public function kbSearch(Request $request)
    {
        $query = $request->input('q', '');
        if (strlen($query) < 3) {
            return response()->json([]);
        }

        $articles = \App\Models\KbArticle::published()
            ->where('title', 'ilike', '%' . $query . '%')
            ->select('id', 'title', 'slug')
            ->limit(6)
            ->get();

        return response()->json($articles);
    }

    public function ticketMessages(Request $request, int $ticketId)
    {
        $session = WidgetChatSession::where('ticket_id', $ticketId)->first();

        if (!$session) {
            return response()->json(['session_id' => null, 'messages' => []]);
        }

        $messages = $session->messages()
            ->with('sender:id,name')
            ->orderBy('created_at')
            ->get()
            ->map(fn($m) => [
                'id'              => $m->id,
                'body'            => $m->body,
                'sender_type'     => $m->sender_type,
                'sender'          => $m->sender ? ['id' => $m->sender->id, 'name' => $m->sender->name] : null,
                'attachment_path' => $m->attachment_path,
                'attachment_name' => $m->attachment_name,
                'created_at'      => $m->created_at->toISOString(),
            ]);

        return response()->json(['session_id' => $session->id, 'messages' => $messages]);
    }

    public function articleFeedback(Request $request, int $articleId)
    {
        $data = $request->validate([
            'helpful' => 'required|boolean',
        ]);

        $article = \App\Models\KbArticle::findOrFail($articleId);
        $userId  = $request->user()?->id;

        if ($userId) {
            \App\Models\KbArticleVote::updateOrCreate(
                ['article_id' => $articleId, 'user_id' => $userId],
                ['is_helpful' => $data['helpful']]
            );
        }

        return response()->json(['message' => 'Feedback registrado.']);
    }
}
