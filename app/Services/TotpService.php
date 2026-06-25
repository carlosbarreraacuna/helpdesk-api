<?php

namespace App\Services;

class TotpService
{
    private const CHARS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public function generateSecret(int $length = 16): string
    {
        $secret = '';
        for ($i = 0; $i < $length; $i++) {
            $secret .= self::CHARS[random_int(0, 31)];
        }
        return $secret;
    }

    public function verify(string $secret, string $code, int $window = 1): bool
    {
        $t = time();
        for ($i = -$window; $i <= $window; $i++) {
            if ($this->computeCode($secret, $t + ($i * 30)) === $code) {
                return true;
            }
        }
        return false;
    }

    public function getOtpAuthUri(string $secret, string $email, string $issuer = 'Cardique Helpdesk'): string
    {
        return sprintf(
            'otpauth://totp/%s:%s?secret=%s&issuer=%s&algorithm=SHA1&digits=6&period=30',
            rawurlencode($issuer),
            rawurlencode($email),
            $secret,
            rawurlencode($issuer)
        );
    }

    private function computeCode(string $secret, int $timestamp): string
    {
        $counter = (int) floor($timestamp / 30);
        $msg     = pack('N*', 0) . pack('N*', $counter);
        $key     = $this->base32Decode($secret);
        $hash    = hash_hmac('sha1', $msg, $key, true);
        $offset  = ord($hash[19]) & 0x0F;
        $code    = (
            ((ord($hash[$offset])     & 0x7F) << 24) |
            ((ord($hash[$offset + 1]) & 0xFF) << 16) |
            ((ord($hash[$offset + 2]) & 0xFF) << 8)  |
             (ord($hash[$offset + 3]) & 0xFF)
        ) % 1_000_000;
        return str_pad((string) $code, 6, '0', STR_PAD_LEFT);
    }

    private function base32Decode(string $input): string
    {
        $map    = array_flip(str_split(self::CHARS));
        $input  = strtoupper(rtrim($input, '='));
        $bitStr = '';
        foreach (str_split($input) as $char) {
            $bitStr .= str_pad(decbin($map[$char] ?? 0), 5, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bitStr, 8) as $byte) {
            if (strlen($byte) === 8) {
                $out .= chr(bindec($byte));
            }
        }
        return $out;
    }
}
