<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TicketCategory extends Model
{
    protected $fillable = ['name', 'description', 'is_active', 'order_index'];

    protected $casts = ['is_active' => 'boolean'];

    public function tickets()
    {
        return $this->hasMany(Ticket::class, 'category_id');
    }

    public function workGroupRules()
    {
        return $this->hasMany(WorkGroupRule::class, 'ticket_category_id');
    }
}
