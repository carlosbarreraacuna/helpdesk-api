<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    protected $token;
    protected $phoneNumberId;
    protected $apiVersion;
    protected $baseUrl;

    public function __construct()
    {
        $this->token = config('services.whatsapp.token');
        $this->phoneNumberId = config('services.whatsapp.phone_number_id');
        $this->apiVersion = config('services.whatsapp.api_version', 'v21.0');
        $this->baseUrl = "https://graph.facebook.com/{$this->apiVersion}";
    }

    /**
     * Enviar mensaje de texto
     */
    public function sendMessage($to, $message)
    {
        return $this->sendRequest([
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'text',
            'text' => [
                'preview_url' => false,
                'body' => $message
            ]
        ]);
    }

    /**
     * Enviar mensaje con botones interactivos
     */
    public function sendInteractiveButtons($to, $bodyText, $buttons)
    {
        $formattedButtons = [];
        foreach ($buttons as $id => $text) {
            $formattedButtons[] = [
                'type' => 'reply',
                'reply' => [
                    'id' => $id,
                    'title' => $text
                ]
            ];
        }

        return $this->sendRequest([
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'interactive',
            'interactive' => [
                'type' => 'button',
                'body' => [
                    'text' => $bodyText
                ],
                'action' => [
                    'buttons' => $formattedButtons
                ]
            ]
        ]);
    }

    /**
     * Enviar mensaje con lista
     */
    public function sendInteractiveList($to, $bodyText, $buttonText, $sections)
    {
        return $this->sendRequest([
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'interactive',
            'interactive' => [
                'type' => 'list',
                'body' => [
                    'text' => $bodyText
                ],
                'action' => [
                    'button' => $buttonText,
                    'sections' => $sections
                ]
            ]
        ]);
    }

    /**
     * Marcar mensaje como leído
     */
    public function markAsRead($messageId)
    {
        return $this->sendRequest([
            'messaging_product' => 'whatsapp',
            'status' => 'read',
            'message_id' => $messageId
        ]);
    }

    /**
     * Enviar request a WhatsApp API
     */
    protected function sendRequest($data)
    {
        try {
            Log::info('WhatsApp API Request', [
                'url' => "{$this->baseUrl}/{$this->phoneNumberId}/messages",
                'phone_number_id' => $this->phoneNumberId,
                'token_length' => strlen($this->token),
                'data' => $data
            ]);

            $response = Http::withToken($this->token)
                ->post("{$this->baseUrl}/{$this->phoneNumberId}/messages", $data);

            Log::info('WhatsApp API Response', [
                'status' => $response->status(),
                'data' => $data,
                'response' => $response->json()
            ]);

            if ($response->failed()) {
                Log::error('WhatsApp API Failed Response', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'data' => $data
                ]);
            }

            return $response->json();
        } catch (\Exception $e) {
            Log::error('WhatsApp API Error', [
                'error' => $e->getMessage(),
                'data' => $data,
                'phone_number_id' => $this->phoneNumberId
            ]);
            throw $e;
        }
    }
}
