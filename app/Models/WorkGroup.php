<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkGroup extends Model
{
    protected $fillable = ['name', 'description', 'priority_order', 'is_active', 'is_default'];

    protected $casts = ['is_active' => 'boolean', 'is_default' => 'boolean'];

    public function agents()
    {
        return $this->belongsToMany(User::class, 'work_group_user');
    }

    public function rules()
    {
        return $this->hasMany(WorkGroupRule::class);
    }

    public function categories()
    {
        return $this->belongsToMany(TicketCategory::class, 'work_group_rules', 'work_group_id', 'ticket_category_id');
    }

    public function tickets()
    {
        return $this->hasMany(Ticket::class);
    }
}
