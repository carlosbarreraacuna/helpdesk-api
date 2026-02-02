<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\WhatsAppService;
use App\Services\WhatsAppBotService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WhatsAppWebhookController extends Controller
{
    protected $whatsapp;
    protected $bot;

    public function __construct(WhatsAppService $whatsapp, WhatsAppBotService $bot)
    {
        $this->whatsapp = $whatsapp;
        $this->bot = $bot;
    }

    /**
     * Verificación del webhook (GET)
     */
    public function verify(Request $request)
    {
        $mode = $request->query('hub_mode');
        $token = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');

        Log::info('Webhook Verification Attempt', [
            'mode' => $mode,
            'token' => $token,
        ]);

        if ($mode === 'subscribe' && $token === config('services.whatsapp.webhook_token')) {
            Log::info('Webhook Verified Successfully');
            return response($challenge, 200);
        }

        Log::warning('Webhook Verification Failed');
        return response('Forbidden', 403);
    }

    /**
     * Recibir mensajes (POST)
     */
    public function webhook(Request $request)
    {
        $data = $request->all();
        
        Log::info('Webhook Received', ['data' => $data]);

        // Verificar estructura del mensaje
        if (!isset($data['entry'][0]['changes'][0]['value'])) {
            return response()->json(['status' => 'ok']);
        }

        $value = $data['entry'][0]['changes'][0]['value'];

        // Procesar solo mensajes entrantes
        if (isset($value['messages'])) {
            foreach ($value['messages'] as $message) {
                $this->processMessage($message, $value['contacts'][0] ?? null);
            }
        }

        // Procesar estados de mensajes (opcional)
        if (isset($value['statuses'])) {
            foreach ($value['statuses'] as $status) {
                Log::info('Message Status Update', $status);
            }
        }

        return response()->json(['status' => 'ok']);
    }

    /**
     * Procesar mensaje individual
     */
    protected function processMessage($message, $contact)
    {
        $from = $message['from'];
        $messageId = $message['id'];
        $messageType = $message['type'];

        // Marcar como leído
        $this->whatsapp->markAsRead($messageId);

        // Obtener contenido del mensaje
        $messageBody = '';
        
        if ($messageType === 'text') {
            $messageBody = $message['text']['body'];
        } elseif ($messageType === 'interactive') {
            if (isset($message['interactive']['button_reply'])) {
                $messageBody = $message['interactive']['button_reply']['id'];
            } elseif (isset($message['interactive']['list_reply'])) {
                $messageBody = $message['interactive']['list_reply']['id'];
            }
        }

        Log::info('Processing Message', [
            'from' => $from,
            'message_id' => $messageId,
            'type' => $messageType,
            'body' => $messageBody,
        ]);

        // Procesar con el bot
        try {
            $this->bot->processMessage($from, $messageBody, $message['wa_id'] ?? $from);
        } catch (\Exception $e) {
            Log::error('Bot Processing Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            $this->whatsapp->sendMessage($from, "❌ Lo siento, ha ocurrido un error. Por favor intenta nuevamente.");
        }
    }

    /**
     * Enviar mensaje manual desde el sistema
     */
    public function sendManualMessage(Request $request)
    {
        $request->validate([
            'to' => 'required|string',
            'message' => 'required|string',
        ]);

        try {
            $response = $this->whatsapp->sendMessage($request->to, $request->message);
            
            return response()->json([
                'success' => true,
                'response' => $response
            ]);
        } catch (\Exception $e) {
            Log::error('Manual Message Error', [
                'error' => $e->getMessage(),
                'to' => $request->to,
                'message' => $request->message
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
