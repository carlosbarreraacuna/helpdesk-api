<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkGroupRule extends Model
{
    protected $fillable = ['work_group_id', 'ticket_category_id'];

    public function workGroup()
    {
        return $this->belongsTo(WorkGroup::class);
    }

    public function category()
    {
        return $this->belongsTo(TicketCategory::class, 'ticket_category_id');
    }
}
