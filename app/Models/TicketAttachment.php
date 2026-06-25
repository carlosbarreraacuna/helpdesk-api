<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class TicketAttachment extends Model
{
    use SoftDeletes;

    protected $fillable = ['ticket_id', 'path', 'name', 'mime', 'size'];

    protected $appends = ['url'];

    public function getUrlAttribute(): string
    {
        try {
            return Storage::disk('s3')->temporaryUrl($this->path, now()->addMinutes(60));
        } catch (\Throwable $e) {
            return '';
        }
    }

    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }
}
