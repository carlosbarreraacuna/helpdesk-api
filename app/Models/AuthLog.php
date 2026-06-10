<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuthLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'login_input',
        'event',
        'ip_address',
        'user_agent',
        'required_permission',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public static function record(
        string $event,
        ?int $userId = null,
        ?string $loginInput = null,
        ?string $requiredPermission = null,
    ): void {
        static::create([
            'user_id'             => $userId,
            'login_input'         => $loginInput,
            'event'               => $event,
            'ip_address'          => request()->ip(),
            'user_agent'          => request()->userAgent(),
            'required_permission' => $requiredPermission,
            'created_at'          => now(),
        ]);
    }
}
