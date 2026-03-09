<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetLocationLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'asset_id', 'from_location_id', 'to_location_id',
        'changed_by', 'reason', 'changed_at',
    ];

    protected $casts = ['changed_at' => 'datetime'];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function fromLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'from_location_id');
    }

    public function toLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'to_location_id');
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
