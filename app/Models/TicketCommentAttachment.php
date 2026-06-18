<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class TicketCommentAttachment extends Model
{
    use SoftDeletes;

    protected $fillable = ['comment_id', 'path', 'name', 'mime'];

    protected $appends = ['url'];

    public function getUrlAttribute(): string
    {
        try {
            return Storage::disk('s3')->url($this->path);
        } catch (\Throwable $e) {
            return '';
        }
    }

    public function comment()
    {
        return $this->belongsTo(TicketComment::class);
    }
}
