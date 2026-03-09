<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssetTypeField extends Model
{
    protected $fillable = [
        'asset_type_id', 'name', 'display_name', 'field_type',
        'options', 'is_required', 'is_identifier', 'order_index',
    ];

    protected $casts = [
        'options'       => 'array',
        'is_required'   => 'boolean',
        'is_identifier' => 'boolean',
    ];

    public function assetType(): BelongsTo
    {
        return $this->belongsTo(AssetType::class);
    }

    public function fieldValues(): HasMany
    {
        return $this->hasMany(AssetFieldValue::class, 'field_id');
    }
}
