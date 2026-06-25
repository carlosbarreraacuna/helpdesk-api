<?php

namespace App\Console\Commands;

use App\Models\EmailChannel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;

class EncryptEmailChannelCredentials extends Command
{
    protected $signature   = 'email-channels:encrypt-credentials';
    protected $description = 'Re-cifra las credenciales de canales de email que estén en texto plano';

    private const FIELDS = [
        'imap_password',
        'smtp_password',
        'gmail_client_secret',
        'gmail_refresh_token',
        'gmail_access_token',
    ];

    public function handle(): int
    {
        $channels = EmailChannel::all();

        if ($channels->isEmpty()) {
            $this->info('No hay canales de email registrados.');
            return self::SUCCESS;
        }

        $migrated = 0;

        foreach ($channels as $channel) {
            $updates = [];

            foreach (self::FIELDS as $field) {
                $raw = $channel->getRawOriginal($field);

                if (blank($raw)) {
                    continue;
                }

                // Si ya está cifrado, Crypt::decrypt no lanza excepción
                try {
                    Crypt::decrypt($raw);
                    // Ya estaba cifrado — nada que hacer
                } catch (\Throwable) {
                    // Texto plano: cifrarlo manualmente en la query
                    // para evitar que el cast lo doble-cifre
                    $updates[$field] = encrypt($raw);
                }
            }

            if (!empty($updates)) {
                // Bypass del modelo para escribir el valor ya cifrado directamente
                \Illuminate\Support\Facades\DB::table('email_channels')
                    ->where('id', $channel->id)
                    ->update($updates);

                $migrated++;
                $this->line("  ✓ Canal #{$channel->id} ({$channel->email}) migrado");
            }
        }

        $this->info("Listo. {$migrated} canal(es) migrado(s).");
        return self::SUCCESS;
    }
}
