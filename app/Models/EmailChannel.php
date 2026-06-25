<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmailChannel extends Model
{
    protected $fillable = [
        'name', 'email', 'display_name',
        'imap_host', 'imap_port', 'imap_encryption', 'imap_username', 'imap_password', 'imap_folder',
        'smtp_host', 'smtp_port', 'smtp_encryption', 'smtp_username', 'smtp_password',
        'is_active', 'last_polled_at', 'last_uid',
        'gmail_client_id', 'gmail_client_secret', 'gmail_refresh_token',
        'gmail_access_token', 'gmail_pubsub_topic', 'gmail_history_id', 'use_gmail_api',
    ];

    protected $hidden = ['imap_password', 'smtp_password', 'gmail_client_secret', 'gmail_refresh_token', 'gmail_access_token'];

    protected $casts = [
        'is_active'        => 'boolean',
        'use_gmail_api'    => 'boolean',
        'last_polled_at'   => 'datetime',
        'gmail_history_id' => 'integer',

        // Cifrado en reposo — AES-256-CBC via APP_KEY
        'imap_password'        => 'encrypted',
        'smtp_password'        => 'encrypted',
        'gmail_client_secret'  => 'encrypted',
        'gmail_refresh_token'  => 'encrypted',
        'gmail_access_token'   => 'encrypted',
    ];

    public function messages(): HasMany
    {
        return $this->hasMany(EmailMessage::class, 'ticket_id');
    }
}
