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
            'name'             => 'required|string|max:100',
            'email'            => 'required|email',
            'display_name'     => 'nullable|string|max:100',
            'imap_host'        => 'required|string',
            'imap_port'        => 'integer|min:1|max:65535',
            'imap_encryption'  => 'in:ssl,tls,notls',
            'imap_username'    => 'required|string',
            'imap_password'    => 'required|string',
            'imap_folder'      => 'string',
        ]);

        $channel = EmailChannel::create($validated);
        return response()->json($channel, 201);
    }

    public function update(Request $request, EmailChannel $emailChannel)
    {
        $validated = $request->validate([
            'name'             => 'nullable|string|max:100',
            'email'            => 'nullable|email',
            'display_name'     => 'nullable|string',
            'imap_host'        => 'nullable|string',
            'imap_port'        => 'nullable|integer|min:1',
            'imap_encryption'  => 'nullable|in:ssl,tls,notls',
            'imap_username'    => 'nullable|string',
            'imap_password'    => 'nullable|string',
            'imap_folder'      => 'nullable|string',
            'is_active'        => 'boolean',
        ]);

        if (empty($validated['imap_password'])) {
            unset($validated['imap_password']);
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

    public function pollNow(Request $request, EmailChannel $emailChannel)
    {
        $since = $request->get('since')
            ? new \DateTime($request->get('since'))
            : new \DateTime('today');

        $limit = (int) $request->get('limit', 50);
        $count = $this->service->pollChannel($emailChannel, $since, $limit);
        return response()->json(['message' => "Se procesaron {$count} email(s) nuevos."]);
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
