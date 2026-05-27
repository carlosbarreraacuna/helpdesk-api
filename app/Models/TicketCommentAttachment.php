<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TicketCommentAttachment extends Model
{
    protected $fillable = ['comment_id', 'path', 'name', 'mime'];

    protected $appends = ['url'];

    public function getUrlAttribute(): string
    {
        $base = rtrim(config('app.url'), '/');
        return $base . '/storage/' . $this->path;
    }

    public function comment()
    {
        return $this->belongsTo(TicketComment::class);
    }
}
