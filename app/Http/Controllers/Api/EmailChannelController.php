<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EmailChannel;
use App\Models\EmailMessage;
use App\Services\EmailChannelService;
use Illuminate\Http\Request;

/**
 * @tags Canales
 */
class EmailChannelController extends Controller
{
    public function __construct(private EmailChannelService $service) {}

    public function index()
    {
        $channels = EmailChannel::orderByDesc('is_active')->orderBy('name')->get();
        return response()->json($channels);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'                => 'required|string|max:100',
            'email'               => 'required|email',
            'display_name'        => 'nullable|string|max:100',
            'gmail_client_id'     => 'required|string',
            'gmail_client_secret' => 'required|string',
            'gmail_pubsub_topic'  => 'nullable|string',
        ]);

        $channel = EmailChannel::create($validated);
        return response()->json($channel, 201);
    }

    public function update(Request $request, EmailChannel $emailChannel)
    {
        $validated = $request->validate([
            'name'                => 'nullable|string|max:100',
            'email'               => 'nullable|email',
            'display_name'        => 'nullable|string',
            'is_active'           => 'boolean',
            'gmail_client_id'     => 'nullable|string',
            'gmail_client_secret' => 'nullable|string',
            'gmail_pubsub_topic'  => 'nullable|string',
        ]);

        if (array_key_exists('gmail_client_secret', $validated) && empty($validated['gmail_client_secret'])) {
            unset($validated['gmail_client_secret']);
        }

        $emailChannel->update($validated);
        return response()->json($emailChannel);
    }

    public function destroy(EmailChannel $emailChannel)
    {
        $emailChannel->delete();
        return response()->json(['message' => 'Canal eliminado']);
    }

    public function testConnection(EmailChannel $emailChannel)
    {
        $result = $this->service->testConnection($emailChannel);
        return response()->json($result, $result['success'] ? 200 : 422);
    }

    public function messages(Request $request, $ticketId)
    {
        $messages = EmailMessage::where('ticket_id', $ticketId)
            ->with('sentBy:id,name')
            ->orderBy('created_at')
            ->get();

        return response()->json($messages);
    }
}
