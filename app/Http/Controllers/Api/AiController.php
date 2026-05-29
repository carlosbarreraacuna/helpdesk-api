<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AiService;
use App\Models\Ticket;
use App\Models\KbArticle;
use App\Models\TicketCategory;
use Illuminate\Http\Request;

class AiController extends Controller
{
    public function __construct(private AiService $ai) {}

    public function classifyTicket(Request $request)
    {
        $request->validate(['description' => 'required|string|min:10']);

        $categories = TicketCategory::select('id', 'name')->get()->toArray();
        $result = $this->ai->classifyTicket($request->description, $categories);

        return response()->json($result);
    }

    public function suggestReply(Request $request, int $ticketId)
    {
        $ticket = Ticket::with(['comments.user', 'status'])->findOrFail($ticketId);
        $user   = $request->user();

        if (!in_array($user->role?->name, ['admin', 'supervisor']) && $ticket->assigned_to !== $user->id) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $reply = $this->ai->suggestReply($ticket);
        return response()->json(['reply' => $reply]);
    }

    public function summarizeTicket(Request $request, int $ticketId)
    {
        $ticket = Ticket::with(['comments.user', 'status'])->findOrFail($ticketId);
        $summary = $this->ai->summarizeTicket($ticket);
        return response()->json(['summary' => $summary]);
    }

    public function kbSearch(Request $request)
    {
        $request->validate(['query' => 'required|string|min:3']);

        $articles = KbArticle::published()
            ->with('category')
            ->select('id', 'title', 'category_id')
            ->get()
            ->map(fn($a) => [
                'id'            => $a->id,
                'title'         => $a->title,
                'category_name' => $a->category?->name ?? '',
            ])
            ->toArray();

        $rankedIds  = $this->ai->semanticKbSearch($request->query('query'), $articles);
        $byId       = collect($articles)->keyBy('id');
        $ranked     = array_values(array_filter(array_map(fn($id) => $byId[$id] ?? null, $rankedIds)));

        return response()->json(['articles' => $ranked]);
    }

    public function widgetChat(Request $request)
    {
        $request->validate([
            'message'      => 'required|string',
            'history'      => 'array',
            'user_context' => 'array',
        ]);

        $kbArticles = KbArticle::published()
            ->with('publishedVersion')
            ->select('id', 'title')
            ->take(20)
            ->get()
            ->map(fn($a) => [
                'id'      => $a->id,
                'title'   => $a->title,
                'snippet' => mb_substr(strip_tags($a->publishedVersion?->content ?? ''), 0, 150),
            ])
            ->toArray();

        $result = $this->ai->widgetChat(
            $request->message,
            $request->input('history', []),
            $request->input('user_context', ['name' => 'Usuario', 'area' => '']),
            $kbArticles
        );

        return response()->json($result);
    }

    public function slaRisk(Request $request)
    {
        $tickets = Ticket::with('status')
            ->whereHas('status', fn($q) => $q->whereNotIn('name', ['cerrado', 'resuelto', 'closed', 'resolved']))
            ->select('id', 'ticket_number', 'priority', 'description', 'sla_due_date')
            ->get()
            ->map(fn($t) => [
                'ticket_number'  => $t->ticket_number,
                'priority'       => $t->priority,
                'status'         => $t->status?->name,
                'description'    => $t->description,
                'sla_hours_left' => $t->sla_due_date ? now()->diffInHours($t->sla_due_date, false) : null,
            ])
            ->toArray();

        $atRisk = $this->ai->analyzeSlaRisk($tickets);
        return response()->json(['at_risk' => $atRisk]);
    }

    public function sentiment(Request $request)
    {
        $request->validate(['messages' => 'required|array']);
        $result = $this->ai->analyzeSentiment($request->messages);
        return response()->json($result);
    }
}
