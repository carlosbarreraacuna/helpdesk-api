<?php

namespace App\Services;

use App\Models\EmailChannel;
use App\Models\EmailMessage;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\TicketCommentAttachment;
use App\Models\TicketHistory;
use App\Models\TicketStatus;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Mail\Message;

class EmailChannelService
{
    public function pollChannel(
        EmailChannel $channel,
        ?\DateTimeInterface $since = null,
        int $limit = 50
    ): int {
        if ($channel->use_gmail_api) {
            Log::info('EmailChannelService: Gmail channels use Pub/Sub push — manual poll skipped.', ['channel' => $channel->id]);
            return 0;
        }

        if (!$channel->imap_host || !$channel->imap_port || !$channel->imap_username) {
            Log::error('EmailChannelService: IMAP not fully configured.', ['channel' => $channel->id]);
            return 0;
        }

        $since ??= new \DateTime('today');

        try {
            $imap = new ImapSocketClient($channel->imap_host, (int) $channel->imap_port, $channel->imap_encryption ?? 'ssl');
            $imap->connect();
            $imap->login($channel->imap_username, $channel->imap_password);
            $imap->selectFolder($channel->imap_folder ?? 'INBOX');
        } catch (\Throwable $e) {
            Log::error('EmailChannelService: connection failed', [
                'channel' => $channel->id,
                'error'   => $e->getMessage(),
            ]);
            return 0;
        }

        $uids = $imap->searchUnseen($since, $limit);
        $processed = 0;

        foreach ($uids as $uid) {
            try {
                $raw  = $imap->fetchBody($uid);
                $created = $this->processRawMessage($raw, $uid, $channel);
                $imap->markSeen($uid);
                if ($created) $processed++;
            } catch (\Throwable $e) {
                Log::error('EmailChannelService: error processing UID', ['uid' => $uid, 'error' => $e->getMessage()]);
            }
        }

        $imap->disconnect();
        $channel->update(['last_polled_at' => now()]);

        return $processed;
    }

    /**
     * Process a raw RFC 2822 email string received directly from Gmail API (no IMAP literal wrapper).
     * The $gmailMessageId is used as fallback Message-ID for deduplication.
     */
    public function processRawEmailDirectly(string $raw, string $gmailMessageId, EmailChannel $channel): bool
    {
        [$headers, $rawBody] = $this->splitHeadersBody($raw);

        $messageId = $this->extractHeader($headers, 'Message-ID') ?? "gmail-{$gmailMessageId}";
        $messageId = trim($messageId, '<> ');

        return $this->processEmailParts($headers, $rawBody, $messageId, $channel);
    }

    private function processRawMessage(string $raw, int $uid, EmailChannel $channel): bool
    {
        $message = $this->extractImapLiteral($raw);
        [$headers, $rawBody] = $this->splitHeadersBody($message);

        $messageId = $this->extractHeader($headers, 'Message-ID') ?? "uid-{$uid}-{$channel->id}";
        $messageId = trim($messageId, '<> ');

        return $this->processEmailParts($headers, $rawBody, $messageId, $channel);
    }

    private function processEmailParts(string $headers, string $rawBody, string $messageId, EmailChannel $channel): bool
    {
        if (EmailMessage::where('message_id', $messageId)->exists()) return false;

        $fromRaw    = $this->extractHeader($headers, 'From') ?? '';
        $fromEmail  = $this->parseEmail($fromRaw);
        $fromName   = $this->parseName($fromRaw) ?: $fromEmail;
        $subject    = $this->decodeHeader($this->extractHeader($headers, 'Subject') ?? '(Sin asunto)');
        $inReplyTo  = trim($this->extractHeader($headers, 'In-Reply-To') ?? '', '<> ');
        $references = trim($this->extractHeader($headers, 'References') ?? '', '<> ');
        $ccRaw      = $this->extractHeader($headers, 'Cc') ?? '';

        // Skip emails sent by the helpdesk itself (outbound replies coming back via Gmail push)
        $ownAddresses = array_filter([
            strtolower($channel->email ?? ''),
            strtolower(config('mail.from.address', '')),
        ]);
        if (in_array(strtolower($fromEmail), $ownAddresses, true)) {
            Log::info('EmailChannel: skipping own outbound email', ['from' => $fromEmail]);
            return false;
        }

        if ($this->isAutomatedEmail($fromEmail, $headers)) {
            Log::info('EmailChannel: filtered automated email', ['from' => $fromEmail, 'subject' => $subject]);
            return false;
        }

        $mimeResult = $this->extractMimeContent($headers, $rawBody);
        $textBody        = $mimeResult['text'];
        $emailAttachments = $mimeResult['attachments'];

        if (empty(trim($textBody))) {
            $textBody = $subject;
        }

        $textBody  = $this->toUtf8($textBody);
        $fromName  = $this->toUtf8($fromName);
        $fromEmail = $this->toUtf8($fromEmail);
        $subject   = $this->toUtf8($subject);

        $ticket = $this->findExistingTicket($inReplyTo, $references, $subject, $fromEmail);

        if ($ticket) {
            $emailComment = TicketComment::create([
                'ticket_id'   => $ticket->id,
                'user_id'     => null,
                'comment'     => $textBody,
                'is_internal' => false,
            ]);
            $this->saveEmailAttachments($emailAttachments, $ticket->id, $emailComment->id);
            $emailComment->load('attachments');
            broadcast(new \App\Events\TicketCommentAdded($emailComment));
            TicketHistory::create([
                'ticket_id' => $ticket->id,
                'action'    => 'email_respuesta',
                'new_value' => "Respuesta por email de {$fromEmail}",
            ]);
        } else {
            $ticketNumber = 'TKT-' . date('Y') . '-' . str_pad(Ticket::count() + 1, 4, '0', STR_PAD_LEFT);
            $newStatus    = TicketStatus::where('name', 'nuevo')->first();

            $ticket = Ticket::create([
                'ticket_number'    => $ticketNumber,
                'requester_name'   => $fromName,
                'requester_email'  => $fromEmail,
                'requester_area'   => 'Email',
                'description'      => $textBody,
                'verification_code'=> rand(100000, 999999),
                'priority'         => 'media',
                'status_id'        => $newStatus->id,
                'channel'          => 'email',
                'channel_ref'      => $messageId,
            ]);

            TicketHistory::create([
                'ticket_id' => $ticket->id,
                'action'    => 'creado',
                'new_value' => "Ticket creado desde email ({$fromEmail}) · Asunto: {$subject}",
            ]);

            // Store email attachments as a system comment on the new ticket
            if (!empty($emailAttachments)) {
                $attComment = TicketComment::create([
                    'ticket_id'   => $ticket->id,
                    'user_id'     => null,
                    'comment'     => '📎 Archivos adjuntos del email',
                    'is_internal' => false,
                ]);
                $this->saveEmailAttachments($emailAttachments, $ticket->id, $attComment->id);
                $attComment->load('attachments');
                broadcast(new \App\Events\TicketCommentAdded($attComment));
            }

            broadcast(new \App\Events\TicketCreated($ticket));
        }

        // Auto-add CC'd users as ticket participants
        if (!empty(trim($ccRaw))) {
            $ccEmails = $this->parseCcEmails($ccRaw);
            foreach ($ccEmails as $ccEmail) {
                $ccUser = \App\Models\User::where('email', $ccEmail)->first();
                if ($ccUser) {
                    \App\Models\TicketParticipant::firstOrCreate(
                        ['ticket_id' => $ticket->id, 'user_id' => $ccUser->id],
                        ['added_by'  => null]
                    );
                }
            }
        }

        EmailMessage::create([
            'ticket_id'  => $ticket->id,
            'message_id' => $messageId,
            'from_email' => $fromEmail,
            'from_name'  => $fromName,
            'subject'    => $subject,
            'body_text'  => $textBody,
            'direction'  => 'inbound',
            'status'     => 'received',
        ]);

        return true;
    }

    private function isAutomatedEmail(string $fromEmail, string $headers): bool
    {
        // Standard spam/bulk headers
        $precedence = strtolower($this->extractHeader($headers, 'Precedence') ?? '');
        if (in_array($precedence, ['bulk', 'list', 'junk'])) return true;

        if ($this->extractHeader($headers, 'List-Unsubscribe')) return true;
        if ($this->extractHeader($headers, 'X-Campaign-Id')) return true;
        if ($this->extractHeader($headers, 'X-Mailchimp-Id')) return true;
        if ($this->extractHeader($headers, 'List-Id')) return true;
        if ($this->extractHeader($headers, 'X-Mailer-LID')) return true;

        $autoSubmitted = strtolower($this->extractHeader($headers, 'Auto-Submitted') ?? '');
        if ($autoSubmitted && $autoSubmitted !== 'no') return true;

        // Automated local-part patterns (only unambiguous ones)
        $automatedLocalParts = [
            'noreply', 'no-reply', 'donotreply', 'do-not-reply',
            'mailer-daemon', 'postmaster', 'newsletter',
            'bounces@', 'bounce@', 'unsubscribe@',
        ];
        foreach ($automatedLocalParts as $pattern) {
            if (str_contains($fromEmail, $pattern)) return true;
        }

        // Automated sender domains (marketing/transactional platforms)
        $automatedDomains = [
            // Email marketing platforms
            'mailchimp.com', 'sendgrid.net', 'amazonses.com', 'mailgun.org',
            'sparkpostmail.com', 'sendpulse.com', 'klaviyo.com', 'constantcontact.com',
            'campaignmonitor.com', 'hubspotemail.net', 'salesforce.com',
            // Notification services
            'notifications.google.com', 'facebookmail.com', 'twitteremail.com',
            'linkedin.com', 'snapchat.com', 'instagram.com',
            // Dev / hosting platforms
            'vercel.com', 'github.com', 'gitlab.com', 'netlify.com', 'heroku.com',
            'render.com', 'railway.app', 'fly.io', 'cloudflare.com', 'digitalocean.com',
            'sentry.io', 'datadog.com', 'newrelic.com', 'pagerduty.com',
            'atlassian.com', 'jira.com', 'confluence.com', 'bitbucket.org',
            'slack.com', 'discord.com', 'zoom.us', 'teams.microsoft.com',
            // E-learning / subscriptions
            'udemy.com', 'students.udemy.com', 'coursera.org', 'edx.org',
            // Colombian banks & commercial
            'mailgrupobancolombia.com.co', 'davivienda.com', 'bancodeoccidente.com.co',
            'avvillas.com.co', 'bbva.com.co', 'itau.com.co',
            // E-commerce
            'amazon.com', 'mercadolibre.com', 'falabella.com', 'exito.com',
        ];
        foreach ($automatedDomains as $domain) {
            if (str_ends_with($fromEmail, '@' . $domain) || str_ends_with($fromEmail, '.' . $domain)) {
                return true;
            }
        }

        // Subdomain patterns for marketing (e.g. em.company.com, mail.company.com, news.company.com)
        if (preg_match('/@(em|mail|email|news|newsletter|marketing|promo|bulk|alert|notifications?)\./i', $fromEmail)) {
            return true;
        }

        return false;
    }

    // ──────────────────────────── MIME parsing ──────────────────────────────

    /**
     * Returns ['text' => string, 'attachments' => [['data'=>binary, 'name'=>string, 'mime'=>string], ...]]
     */
    private function extractMimeContent(string $headers, string $body): array
    {
        $contentType = $this->extractHeader($headers, 'Content-Type') ?? 'text/plain';
        $transferEnc = $this->extractHeader($headers, 'Content-Transfer-Encoding') ?? '';

        if (!preg_match('/multipart\//i', $contentType)) {
            $decoded = $this->decodeBody($body, $transferEnc);
            $text = preg_match('/text\/html/i', $contentType)
                ? $this->htmlToText($decoded)
                : $this->cleanText($decoded);
            return ['text' => $text, 'attachments' => []];
        }

        if (!preg_match('/boundary\s*=\s*"?([^";\s\r\n]+)"?/i', $contentType, $bm)) {
            return ['text' => $this->cleanText($body), 'attachments' => []];
        }

        return $this->parseMultipart($body, $bm[1]);
    }

    /** Keep old signature as a convenience wrapper used by nothing else. */
    private function extractTextFromMime(string $headers, string $body): string
    {
        return $this->extractMimeContent($headers, $body)['text'];
    }

    /** Returns ['text' => string, 'attachments' => [...]] */
    private function parseMultipart(string $body, string $boundary): array
    {
        $body = str_replace("\r\n", "\n", $body);

        $delimiter = '--' . $boundary;
        $parts = explode($delimiter, $body);

        $plainText   = '';
        $htmlText    = '';
        $attachments = [];

        foreach ($parts as $part) {
            $part = ltrim($part, "\n");

            if (empty(trim($part)) || $part === '--' || str_starts_with(trim($part), '--')) {
                continue;
            }

            [$partHeaders, $partBody] = $this->splitHeadersBody($part);
            if (empty(trim($partHeaders))) continue;

            $partType = strtolower($this->extractHeader($partHeaders, 'Content-Type') ?? '');
            $partEnc  = $this->extractHeader($partHeaders, 'Content-Transfer-Encoding') ?? '';

            // Recurse into nested multipart
            if (preg_match('/multipart\//i', $partType)) {
                if (preg_match('/boundary\s*=\s*"?([^";\s\r\n]+)"?/i', $partType, $nb)) {
                    $nested = $this->parseMultipart($partBody, $nb[1]);
                    if (!empty($nested['text']) && empty($plainText)) {
                        $plainText = $nested['text'];
                    }
                    $attachments = array_merge($attachments, $nested['attachments'] ?? []);
                }
                continue;
            }

            // text/plain — preferred
            if (str_contains($partType, 'text/plain') && empty($plainText)) {
                $decoded = $this->decodeBody($partBody, $partEnc);
                $decoded = $this->convertPartCharset($decoded, $partType);
                $text = $this->cleanText($decoded);
                if (!empty($text)) $plainText = $text;
                continue;
            }

            // text/html — fallback
            if (str_contains($partType, 'text/html') && empty($htmlText)) {
                $decoded = $this->decodeBody($partBody, $partEnc);
                $decoded = $this->convertPartCharset($decoded, $partType);
                $text = $this->htmlToText($decoded);
                if (!empty($text)) $htmlText = $text;
                continue;
            }

            // Image or generic file attachment
            $disposition = strtolower($this->extractHeader($partHeaders, 'Content-Disposition') ?? '');
            $isAttachment = str_contains($disposition, 'attachment') || str_contains($disposition, 'inline');
            $isImage = str_contains($partType, 'image/');

            if ($isAttachment || $isImage) {
                $decoded = $this->decodeBody($partBody, $partEnc);
                if (empty($decoded)) continue;

                // Extract filename from Content-Disposition or Content-Type
                $filename = null;
                if (preg_match('/filename\s*=\s*"?([^";\r\n]+)"?/i', $partHeaders, $fn)) {
                    $filename = trim($fn[1], '"');
                }
                if (!$filename && preg_match('/name\s*=\s*"?([^";\r\n]+)"?/i', $partType, $fn)) {
                    $filename = trim($fn[1], '"');
                }
                if (!$filename) {
                    $ext = explode('/', explode(';', $partType)[0])[1] ?? 'bin';
                    $filename = 'attachment_' . uniqid() . '.' . trim($ext);
                }

                // Sanitize filename
                $filename = preg_replace('/[^a-zA-Z0-9._\-]/', '_', basename($filename));
                $mime = trim(explode(';', $partType)[0]);

                $attachments[] = ['data' => $decoded, 'name' => $filename, 'mime' => $mime];
            }
        }

        return ['text' => $plainText ?: $htmlText ?: '', 'attachments' => $attachments];
    }

    private function htmlToText(string $html): string
    {
        // Convert common block elements to newlines before stripping tags
        $html = preg_replace('/<br\s*\/?>/i', "\n", $html);
        $html = preg_replace('/<\/?(p|div|tr|li|h[1-6])[^>]*>/i', "\n", $html);
        $html = strip_tags($html);
        return $this->cleanText($html);
    }

    private function cleanText(string $text): string
    {
        // Decode HTML entities
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        // Collapse excessive whitespace/blank lines
        $text = preg_replace("/(\r?\n){3,}/", "\n\n", $text);
        return trim($text);
    }

    // ──────────────────────────── send reply ────────────────────────────────

    /**
     * @param  \App\Models\TicketCommentAttachment[]|\Illuminate\Database\Eloquent\Collection  $attachments
     */
    public function sendReply(Ticket $ticket, string $replyBody, ?int $agentId = null, $attachments = []): bool
    {
        try {
            $to      = $ticket->requester_email;

            // Get original message-id for threading (In-Reply-To / References)
            $originalMessageId = $ticket->channel_ref
                ? '<' . $ticket->channel_ref . '>'
                : null;

            // Use the original subject from the first inbound email, fallback to description
            $originalEmail = EmailMessage::where('ticket_id', $ticket->id)
                ->where('direction', 'inbound')
                ->orderBy('id')
                ->first();

            $subject = $originalEmail
                ? (str_starts_with($originalEmail->subject, 'Re:') ? $originalEmail->subject : 'Re: ' . $originalEmail->subject)
                : 'Re: [' . $ticket->ticket_number . '] ' . mb_substr($ticket->description, 0, 60);

            Mail::raw($replyBody, function (Message $message) use ($to, $subject, $originalMessageId, $attachments) {
                $message->to($to)->subject($subject);
                if ($originalMessageId) {
                    $message->getHeaders()->addTextHeader('In-Reply-To', $originalMessageId);
                    $message->getHeaders()->addTextHeader('References', $originalMessageId);
                }
                foreach ($attachments as $att) {
                    $fullPath = Storage::disk('public')->path($att->path);
                    if (file_exists($fullPath)) {
                        $message->attach($fullPath, [
                            'as'   => $att->name,
                            'mime' => $att->mime ?? mime_content_type($fullPath),
                        ]);
                    }
                }
            });

            EmailMessage::create([
                'ticket_id'  => $ticket->id,
                'message_id' => 'reply-' . $ticket->ticket_number . '-' . time() . '@helpdesk',
                'from_email' => config('mail.from.address'),
                'from_name'  => config('mail.from.name'),
                'subject'    => $subject,
                'body_text'  => $replyBody,
                'direction'  => 'outbound',
                'status'     => 'sent',
                'sent_by'    => $agentId,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('EmailChannelService: sendReply failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    // ──────────────────────────── test connection ───────────────────────────

    public function testConnection(EmailChannel $channel): array
    {
        if ($channel->use_gmail_api) {
            return $this->testGmailConnection($channel);
        }

        if (!$channel->imap_host || !$channel->imap_port || !$channel->imap_username) {
            return ['success' => false, 'message' => 'Faltan datos IMAP: host, puerto o usuario no configurados.'];
        }

        try {
            $imap = new ImapSocketClient($channel->imap_host, (int) $channel->imap_port, $channel->imap_encryption ?? 'ssl');
            $imap->connect();
            $imap->login($channel->imap_username, $channel->imap_password);
            $total = $imap->selectFolder($channel->imap_folder ?? 'INBOX');
            $imap->disconnect();

            return ['success' => true, 'message' => "Conexión exitosa. {$total} mensajes en la bandeja."];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    private function testGmailConnection(EmailChannel $channel): array
    {
        if (!$channel->gmail_access_token) {
            return ['success' => false, 'message' => 'Canal Gmail no autorizado. Completa el flujo OAuth primero.'];
        }

        try {
            $response = \Illuminate\Support\Facades\Http::withToken($channel->gmail_access_token)
                ->get('https://gmail.googleapis.com/gmail/v1/users/me/profile');

            if ($response->status() === 401) {
                return ['success' => false, 'message' => 'Token de Gmail expirado. Vuelve a autorizar el canal.'];
            }

            if (!$response->successful()) {
                return ['success' => false, 'message' => 'Error al conectar con Gmail: ' . $response->body()];
            }

            $email = $response->json('emailAddress', $channel->email);
            return ['success' => true, 'message' => "Conexión Gmail exitosa. Cuenta: {$email}"];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Error de conexión: ' . $e->getMessage()];
        }
    }

    // ──────────────────────────── low-level helpers ─────────────────────────

    private function extractImapLiteral(string $raw): string
    {
        // IMAP fetch response wraps the message in {size}\r\n<literal>
        // Use \r?\n to handle both CRLF and LF
        if (preg_match('/\{(\d+)\}\r?\n(.*)/s', $raw, $m)) {
            return substr($m[2], 0, (int)$m[1]);
        }
        return $raw;
    }

    private function splitHeadersBody(string $message): array
    {
        // Normalize to LF then split on blank line
        $message = str_replace("\r\n", "\n", $message);
        $pos = strpos($message, "\n\n");
        if ($pos === false) {
            return [$message, ''];
        }
        return [substr($message, 0, $pos), substr($message, $pos + 2)];
    }

    private function extractHeader(string $headers, string $name): ?string
    {
        // Normalize CRLF → LF before matching
        $headers = str_replace("\r\n", "\n", $headers);
        $pattern = '/^' . preg_quote($name, '/') . ':\s*(.*?)(?=\n\S|\n\n|$)/ims';
        if (preg_match($pattern, $headers, $m)) {
            // Unfold folded header lines
            return preg_replace('/\n\s+/', ' ', trim($m[1]));
        }
        return null;
    }

    private function decodeHeader(string $value): string
    {
        return preg_replace_callback(
            '/=\?([^?]+)\?([BQ])\?([^?]*)\?=/i',
            function ($m) {
                $charset = $m[1];
                $encoded = strtoupper($m[2]) === 'B'
                    ? base64_decode($m[3])
                    : quoted_printable_decode(str_replace('_', ' ', $m[3]));
                return mb_convert_encoding($encoded, 'UTF-8', $charset);
            },
            $value
        ) ?? $value;
    }

    private function parseEmail(string $from): string
    {
        if (preg_match('/<([^>]+)>/', $from, $m)) return strtolower(trim($m[1]));
        if (filter_var(trim($from), FILTER_VALIDATE_EMAIL)) return strtolower(trim($from));
        return strtolower(trim($from));
    }

    private function parseCcEmails(string $cc): array
    {
        $emails = [];
        // Split by comma — each entry is "Name <email>" or just "email"
        foreach (preg_split('/,(?![^<>]*>)/', $cc) as $entry) {
            $email = $this->parseEmail(trim($entry));
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $emails[] = strtolower($email);
            }
        }
        return array_unique($emails);
    }

    private function saveEmailAttachments(array $attachments, int $ticketId, int $commentId): void
    {
        foreach ($attachments as $att) {
            if (empty($att['data'])) continue;
            $filename = $att['name'] ?? ('attachment_' . uniqid() . '.bin');
            $path = "attachments/tickets/{$ticketId}/" . $filename;
            try {
                Storage::disk('public')->put($path, $att['data']);
                TicketCommentAttachment::create([
                    'comment_id' => $commentId,
                    'path'       => $path,
                    'name'       => $filename,
                    'mime'       => $att['mime'] ?? 'application/octet-stream',
                ]);
            } catch (\Throwable $e) {
                Log::warning("Failed to save email attachment {$filename}: " . $e->getMessage());
            }
        }
    }

    private function parseName(string $from): string
    {
        if (preg_match('/^"?([^"<]+)"?\s*</', $from, $m)) return $this->decodeHeader(trim($m[1], '" '));
        return '';
    }

    private function decodeBody(string $body, string $encoding): string
    {
        $enc = strtolower(trim($encoding));
        if ($enc === 'base64') {
            return base64_decode(preg_replace('/\s+/', '', $body));
        }
        if ($enc === 'quoted-printable') {
            return quoted_printable_decode($body);
        }
        return $body;
    }

    private function convertPartCharset(string $text, string $contentType): string
    {
        if (preg_match('/charset\s*=\s*"?([^";\s\r\n]+)"?/i', $contentType, $m)) {
            $charset = strtoupper(trim($m[1]));
            if ($charset !== 'UTF-8' && $charset !== 'US-ASCII') {
                $converted = @mb_convert_encoding($text, 'UTF-8', $charset);
                if ($converted !== false) return $converted;
            }
        }
        return $text;
    }

    private function toUtf8(string $text): string
    {
        if (mb_check_encoding($text, 'UTF-8')) {
            return $text;
        }
        // Detect encoding and convert
        $detected = mb_detect_encoding($text, ['UTF-8', 'ISO-8859-1', 'Windows-1252', 'ISO-8859-15'], true);
        if ($detected && $detected !== 'UTF-8') {
            $converted = mb_convert_encoding($text, 'UTF-8', $detected);
            if (mb_check_encoding($converted, 'UTF-8')) {
                return $converted;
            }
        }
        // Last resort: iconv with //IGNORE drops invalid bytes
        $result = @iconv('ISO-8859-1', 'UTF-8//IGNORE', $text);
        return $result !== false ? $result : preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x80-\xFF]/', '', $text);
    }

    private function findExistingTicket(
        string $inReplyTo,
        string $references,
        string $subject,
        string $fromEmail
    ): ?Ticket {
        foreach ([$inReplyTo, $references] as $ref) {
            if ($ref) {
                $t = Ticket::where('channel_ref', $ref)->first();
                if ($t) return $t;
            }
        }
        if (preg_match('/TKT-\d{4}-\d{4}/', $subject, $m)) {
            $t = Ticket::where('ticket_number', $m[0])->first();
            if ($t) return $t;
        }
        return null;
    }
}
