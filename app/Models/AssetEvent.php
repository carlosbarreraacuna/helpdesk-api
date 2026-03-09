<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'asset_id', 'event_type', 'description', 'performed_by', 'metadata', 'occurred_at',
    ];

    protected $casts = [
        'metadata'    => 'array',
        'occurred_at' => 'datetime',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
