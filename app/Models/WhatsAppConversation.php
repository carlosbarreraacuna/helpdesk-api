<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsAppConversation extends Model
{
    protected $table = 'whatsapp_conversations';
    
    protected $fillable = [
        'phone_number',
        'wa_id',
        'user_id',
        'state',
        'context',
        'is_authenticated',
        'last_interaction_at',
    ];

    protected $casts = [
        'context' => 'array',
        'is_authenticated' => 'boolean',
        'last_interaction_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function messages()
    {
        return $this->hasMany(WhatsAppMessage::class, 'conversation_id');
    }

    public function updateState($newState, $context = null)
    {
        $data = [
            'state' => $newState,
            'last_interaction_at' => now(),
        ];

        if ($context !== null) {
            $data['context'] = array_merge($this->context ?? [], $context);
        }

        $this->update($data);
    }

    public function getContextValue($key, $default = null)
    {
        return $this->context[$key] ?? $default;
    }

    public function clearContext()
    {
        $this->update(['context' => null]);
    }
}
