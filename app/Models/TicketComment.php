<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TicketComment extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_id', 'user_id', 'comment', 'is_internal',
        'attachment_path', 'attachment_name', 'attachment_mime',
    ];

    protected $casts = [
        'is_internal' => 'boolean',
    ];

    protected $appends = ['attachment_url'];

    public function getAttachmentUrlAttribute(): ?string
    {
        return $this->attachment_path
            ? rtrim(config('app.url'), '/') . '/storage/' . $this->attachment_path
            : null;
    }

    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function attachments()
    {
        return $this->hasMany(TicketCommentAttachment::class, 'comment_id');
    }
}
