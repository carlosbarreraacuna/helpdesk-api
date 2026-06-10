<?php

namespace App\Http\Controllers\Api\Widget;

use App\Http\Controllers\Controller;
use App\Models\WidgetChatSession;
use App\Services\Widget\WidgetChatService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * @tags Widget / Chat
 */
class ChatController extends Controller
{
    public function __construct(private WidgetChatService $chatService) {}

    public function sendMessage(Request $request, WidgetChatSession $session)
    {
        $user = $request->user();

        Gate::authorize('participate', $session);

        $data = $request->validate([
            'body'       => 'required|string|max:4000',
            'attachment' => 'nullable|file|max:10240',
        ]);

        $attachmentPath = null;
        $attachmentName = null;
        if ($request->hasFile('attachment')) {
            $file           = $request->file('attachment');
            $attachmentPath = $file->store('widget-attachments', 'public');
            $attachmentName = $file->getClientOriginalName();
        }

        $message = $this->chatService->sendMessage($session, $user, $data['body'], $attachmentPath, $attachmentName);

        return response()->json($message->load('sender:id,name'));
    }

    public function messages(Request $request, WidgetChatSession $session)
    {
        Gate::authorize('participate', $session);

        $messages = $session->messages()
            ->with('sender:id,name')
            ->orderBy('created_at')
            ->get();

        $this->chatService->markMessagesRead($session, $request->user());

        return response()->json($messages);
    }

    public function markRead(Request $request, WidgetChatSession $session)
    {
        Gate::authorize('participate', $session);
        $this->chatService->markMessagesRead($session, $request->user());
        return response()->json(['message' => 'Mensajes marcados como leídos.']);
    }

    public function assign(Request $request, WidgetChatSession $session)
    {
        $data = $request->validate([
            'agent_id' => 'required|exists:users,id',
        ]);

        $agent   = \App\Models\User::findOrFail($data['agent_id']);
        $session = $this->chatService->assignAgent($session, $agent);

        return response()->json($session);
    }

    public function close(Request $request, WidgetChatSession $session)
    {
        $session = $this->chatService->closeSession($session);
        return response()->json($session);
    }

    public function activeSessions(Request $request)
    {
        $sessions = $this->chatService->getAgentActiveSessions();
        return response()->json($sessions);
    }

    public function unreadCount(Request $request)
    {
        $user = $request->user();

        $count = \App\Models\WidgetChatMessage::whereHas('session', function ($q) use ($user) {
            $q->where('assigned_agent_id', $user->id)->whereIn('status', ['pending', 'active']);
        })
            ->where('sender_type', 'user')
            ->where('is_read', false)
            ->count();

        return response()->json(['unread_count' => $count]);
    }
}
