<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TicketValidation extends Model
{
    protected $fillable = [
        'ticket_id',
        'action',
        'comment',
        'performed_by_email',
        'performed_by_user_id',
    ];

    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }

    public function performedBy()
    {
        return $this->belongsTo(User::class, 'performed_by_user_id');
    }
}
