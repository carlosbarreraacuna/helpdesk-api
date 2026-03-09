<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssetType extends Model
{
    protected $fillable = ['name', 'display_name', 'icon', 'is_system', 'is_active'];

    protected $casts = [
        'is_system' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function fields(): HasMany
    {
        return $this->hasMany(AssetTypeField::class)->orderBy('order_index');
    }

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }
}
