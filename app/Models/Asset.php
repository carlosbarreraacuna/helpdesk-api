<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Area;

class Asset extends Model
{
    protected $fillable = [
        'asset_type_id', 'name', 'internal_code', 'serial_number', 'inventory_tag',
        'status', 'area_id', 'current_user_id', 'vendor', 'purchase_date',
        'warranty_end_date', 'warranty_provider', 'notes', 'is_shared', 'created_by',
    ];

    protected $casts = [
        'is_shared'        => 'boolean',
        'purchase_date'    => 'date',
        'warranty_end_date'=> 'date',
    ];

    public function assetType(): BelongsTo
    {
        return $this->belongsTo(AssetType::class);
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function currentUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'current_user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function fieldValues(): HasMany
    {
        return $this->hasMany(AssetFieldValue::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(AssetAssignment::class);
    }

    public function activeAssignment()
    {
        return $this->hasOne(AssetAssignment::class)->where('is_active', true)->latestOfMany();
    }

    public function areaLogs(): HasMany
    {
        return $this->hasMany(AssetLocationLog::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(AssetEvent::class)->orderByDesc('occurred_at');
    }

    public function maintenances(): HasMany
    {
        return $this->hasMany(AssetMaintenance::class);
    }
}
