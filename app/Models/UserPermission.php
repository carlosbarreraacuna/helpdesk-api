<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserPermission extends Model
{
    use HasFactory;

    protected $table = 'user_permission';
    
    protected $fillable = [
        'user_id',
        'permission_id',
        'is_granted',
        'conditions',
    ];

    protected $casts = [
        'is_granted' => 'boolean',
        'conditions' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function permission()
    {
        return $this->belongsTo(Permission::class);
    }
}
