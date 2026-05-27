<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GoogleCalendarToken extends Model
{
    protected $fillable = ['access_token', 'refresh_token', 'token_type', 'expires_at', 'email'];

    public function isExpired(): bool
    {
        return $this->expires_at < time();
    }
}
