<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use App\Models\Ticket;

class AiService
{
    private string $provider;

    // Anthropic
    private string $anthropicModel  = 'claude-haiku-4-5-20251001';
    private string $anthropicUrl    = 'https://api.anthropic.com/v1/messages';

    // Groq (OpenAI-compatible, gratuito)
    private string $groqModel = 'llama-3.1-8b-instant';
    private string $groqUrl   = 'https://api.groq.com/openai/v1/chat/completions';

    public function __construct()
    {
        $this->provider = config('services.ai.provider', 'groq');
    }

    private function call(string $system, string $user, int $maxTokens = 500): string
    {
        if ($this->provider === 'anthropic') {
            return $this->callAnthropic($system, $user, $maxTokens);
        }
        return $this->callGroq($system, $user, $maxTokens);
    }

    private function callAnthropic(string $system, string $user, int $maxTokens): string
    {
        $response = Http::withHeaders([
            'x-api-key'         => config('services.anthropic.key'),
            'anthropic-version' => '2023-06-01',
            'content-type'      => 'application/json',
        ])->post($this->anthropicUrl, [
            'model'      => $this->anthropicModel,
            'max_tokens' => $maxTokens,
            'system'     => $system,
            'messages'   => [['role' => 'user', 'content' => $user]],
        ]);

        if (!$response->successful()) {
            throw new \Exception('Error Anthropic: ' . $response->body());
        }

        return $response->json('content.0.text', '');
    }

    private function callGroq(string $system, string $user, int $maxTokens): string
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . config('services.groq.key'),
            'Content-Type'  => 'application/json',
        ])->post($this->groqUrl, [
            'model'      => $this->groqModel,
            'max_tokens' => $maxTokens,
            'messages'   => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user',   'content' => $user],
            ],
        ]);

        if (!$response->successful()) {
            throw new \Exception('Error Groq: ' . $response->body());
        }

        return $response->json('choices.0.message.content', '');
    }

    private function extractJson(string $text): ?array
    {
        preg_match('/\{.*\}/s', $text, $matches);
        if (empty($matches[0])) return null;
        return json_decode($matches[0], true);
    }

    public function classifyTicket(string $description, array $categories): array
    {
        $catList = empty($categories)
            ? 'Sin categorías'
            : implode(', ', array_map(fn($c) => "{$c['id']}: {$c['name']}", $categories));

        $system = 'Eres un clasificador de tickets de soporte para CARDIQUE (autoridad ambiental). Responde SOLO con JSON válido, sin texto extra.';
        $user = "Clasifica este ticket:\n\nDESCRIPCIÓN: {$description}\n\nCATEGORÍAS: {$catList}\n\nResponde exactamente:\n{\"priority\":\"baja|media|alta\",\"category_id\":null,\"reason\":\"explicación corta\"}\n\nPrioridad alta=urgencia/sistemas caídos, media=afecta trabajo, baja=consultas/menores.";

        $result = $this->extractJson($this->call($system, $user, 200));
        return $result ?? ['priority' => 'media', 'category_id' => null, 'reason' => ''];
    }

    public function suggestReply(Ticket $ticket): string
    {
        $comments = $ticket->comments()
            ->with('user')
            ->orderBy('created_at', 'asc')
            ->take(10)
            ->get();

        $history = "TICKET #{$ticket->ticket_number}\nSOLICITANTE: {$ticket->requester_name} ({$ticket->requester_area})\nDESCRIPCIÓN: {$ticket->description}\n\nCONVERSACIÓN:\n";
        foreach ($comments as $c) {
            $who   = $c->user?->name ?? $ticket->requester_name;
            $flag  = $c->is_internal ? ' [INTERNO]' : '';
            $history .= "[{$who}]{$flag}: {$c->comment}\n";
        }

        $system = 'Eres agente de soporte de CARDIQUE. Redacta respuestas profesionales, claras y empáticas en español formal. Sin markdown, solo texto plano.';
        $user   = "{$history}\nRedacta una respuesta profesional al solicitante. Sé directo, útil, y pide información adicional si hace falta.";

        return $this->call($system, $user, 400);
    }

    public function summarizeTicket(Ticket $ticket): string
    {
        $comments = $ticket->comments()->with('user')->orderBy('created_at', 'asc')->get();

        $content = "TICKET #{$ticket->ticket_number} | {$ticket->priority} | {$ticket->status?->name}\nSOLICITANTE: {$ticket->requester_name} ({$ticket->requester_area})\nDESCRIPCIÓN: {$ticket->description}\n";
        foreach ($comments as $c) {
            $who = $c->user?->name ?? $ticket->requester_name;
            $content .= "- [{$who}]: {$c->comment}\n";
        }

        $system = 'Asistente que resume tickets de soporte. Conciso y preciso. Responde en español.';
        $user   = "{$content}\nResume en máximo 3 oraciones: qué pide el usuario, qué se hizo, estado actual.";

        return $this->call($system, $user, 300);
    }

    public function semanticKbSearch(string $query, array $articles): array
    {
        if (empty($articles)) return [];

        $list = implode("\n", array_map(
            fn($a) => "ID:{$a['id']} TÍTULO:{$a['title']} CAT:{$a['category_name']}",
            $articles
        ));

        $system = 'Buscador semántico de KB. Responde SOLO con JSON válido.';
        $user   = "CONSULTA: \"{$query}\"\n\nARTÍCULOS:\n{$list}\n\nDevuelve los IDs más relevantes (máx 6), ordenados por relevancia:\n{\"ids\":[1,2,3]}";

        $result = $this->extractJson($this->call($system, $user, 150));
        return $result['ids'] ?? [];
    }

    public function widgetChat(string $message, array $history, array $userCtx, array $kbArticles): array
    {
        $kbList = implode("\n", array_map(
            fn($a) => "- [{$a['id']}] {$a['title']}: {$a['snippet']}",
            array_slice($kbArticles, 0, 8)
        ));

        $histText = implode("\n", array_map(
            fn($h) => "[{$h['from']}]: {$h['text']}",
            array_slice($history, -6)
        ));

        $name = $userCtx['name'] ?? 'Usuario';
        $area = $userCtx['area'] ?? '';

        $system = 'Eres el asistente virtual de CARDIQUE. Ayudas funcionarios con problemas técnicos. Sé amigable, conciso y útil. Responde en español. Si no puedes resolver, sugiere crear un ticket. Responde SOLO con JSON válido.';
        $user   = "USUARIO: {$name} | Área: {$area}\n\nARTÍCULOS KB DISPONIBLES:\n{$kbList}\n\nHISTORIAL:\n{$histText}\n\nMENSAJE: {$message}\n\nResponde con:\n{\"reply\":\"respuesta\",\"suggest_ticket\":false,\"article_id\":null}";

        $result = $this->extractJson($this->call($system, $user, 400));
        return $result ?? ['reply' => 'Lo siento, no pude procesar tu mensaje. ¿Deseas hablar con un agente?', 'suggest_ticket' => false, 'article_id' => null];
    }

    public function analyzeSlaRisk(array $tickets): array
    {
        if (empty($tickets)) return [];

        $list = implode("\n", array_map(function ($t) {
            $hrs = $t['sla_hours_left'] ?? 'sin SLA';
            return "#{$t['ticket_number']} | {$t['priority']} | {$t['status']} | SLA: {$hrs}h | " . mb_substr($t['description'], 0, 80);
        }, $tickets));

        $system = 'Analista de riesgos SLA para helpdesk. Responde SOLO con JSON válido.';
        $user   = "TICKETS ABIERTOS:\n{$list}\n\nIdentifica los tickets en mayor riesgo de incumplir SLA:\n{\"at_risk\":[{\"ticket_number\":\"TKT-001\",\"risk_level\":\"alto|medio\",\"reason\":\"razón breve\"}]}";

        $result = $this->extractJson($this->call($system, $user, 600));
        return $result['at_risk'] ?? [];
    }

    public function analyzeSentiment(array $messages): array
    {
        if (empty($messages)) return ['overall' => 'neutral', 'score' => 0.5, 'alert' => false];

        $text = implode("\n", array_map(
            fn($m) => "[{$m['from']}]: {$m['text']}",
            array_slice($messages, -8)
        ));

        $system = 'Analiza sentimiento de conversaciones de soporte. Responde SOLO con JSON válido.';
        $user   = "CONVERSACIÓN:\n{$text}\n\nAnaliza el sentimiento del USUARIO (no del agente):\n{\"overall\":\"positivo|neutral|negativo|frustrado\",\"score\":0.5,\"alert\":false}";

        $result = $this->extractJson($this->call($system, $user, 150));
        return $result ?? ['overall' => 'neutral', 'score' => 0.5, 'alert' => false];
    }
}
