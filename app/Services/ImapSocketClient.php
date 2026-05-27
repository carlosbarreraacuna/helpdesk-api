<?php

namespace App\Services;

use RuntimeException;

/**
 * Minimal IMAP client over SSL sockets.
 * No ext-imap required — uses PHP's built-in stream_socket_client with OpenSSL.
 */
class ImapSocketClient
{
    private $socket = null;
    private int $seq = 0;

    public function __construct(
        private string $host,
        private int    $port,
        private string $encryption,
    ) {}

    public function connect(): void
    {
        $proto = in_array(strtolower($this->encryption), ['ssl', 'tls']) ? 'ssl' : 'tcp';
        $dsn   = "{$proto}://{$this->host}:{$this->port}";

        $ctx = stream_context_create([
            'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
        ]);

        $this->socket = @stream_socket_client($dsn, $errno, $errstr, 30, STREAM_CLIENT_CONNECT, $ctx);

        if (!$this->socket) {
            throw new RuntimeException("Cannot connect to {$dsn}: [{$errno}] {$errstr}");
        }

        stream_set_timeout($this->socket, 30);
        $this->readLine(); // server greeting
    }

    public function login(string $username, string $password): void
    {
        $res = $this->command("LOGIN \"" . addslashes($username) . "\" \"" . addslashes($password) . "\"");
        if (!str_contains(strtoupper($res), 'OK')) {
            throw new RuntimeException("IMAP LOGIN failed: {$res}");
        }
    }

    public function selectFolder(string $folder = 'INBOX'): int
    {
        $res = $this->command("SELECT \"{$folder}\"");
        if (preg_match('/\* (\d+) EXISTS/', $res, $m)) {
            return (int) $m[1];
        }
        return 0;
    }

    /**
     * Returns array of UIDs for unseen messages since a given date.
     * $since: PHP DateTime or null (defaults to last 30 days)
     */
    public function searchUnseen(?\DateTimeInterface $since = null, int $limit = 50): array
    {
        // Search all UNSEEN messages, take the most recent by UID
        // The EmailChannelService deduplication (via message_id) prevents reprocessing
        $res = $this->command("UID SEARCH UNSEEN");
        if (preg_match('/\* SEARCH([\d ]*)/i', $res, $m)) {
            $uids = array_filter(explode(' ', trim($m[1])));
            $uids = array_values(array_map('intval', $uids));
            rsort($uids); // highest UIDs first = most recent emails
            return array_slice($uids, 0, $limit);
        }
        return [];
    }

    /**
     * Fetch envelope + headers for a UID.
     */
    public function fetchHeaders(int $uid): string
    {
        return $this->command("UID FETCH {$uid} BODY.PEEK[HEADER]");
    }

    /**
     * Fetch full message body for a UID.
     */
    public function fetchBody(int $uid): string
    {
        return $this->command("UID FETCH {$uid} BODY[]");
    }

    /**
     * Mark a UID as seen.
     */
    public function markSeen(int $uid): void
    {
        $this->command("UID STORE {$uid} +FLAGS (\\Seen)");
    }

    public function rawCommand(string $cmd): string
    {
        return $this->command($cmd);
    }

    public function disconnect(): void
    {
        if ($this->socket) {
            $this->command('LOGOUT');
            fclose($this->socket);
            $this->socket = null;
        }
    }

    private function command(string $cmd): string
    {
        $tag = 'A' . str_pad(++$this->seq, 4, '0', STR_PAD_LEFT);
        fwrite($this->socket, "{$tag} {$cmd}\r\n");
        return $this->readUntilTag($tag);
    }

    private function readLine(): string
    {
        return fgets($this->socket, 8192) ?: '';
    }

    private function readUntilTag(string $tag): string
    {
        $buffer = '';
        $limit  = 2000;
        while ($limit-- > 0) {
            $line = fgets($this->socket, 65536);
            if ($line === false) break;
            $buffer .= $line;
            if (str_starts_with($line, $tag)) break;
        }
        return $buffer;
    }
}
