<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceProvider extends Model
{
    protected $fillable = ['name', 'phone', 'email', 'service_type', 'notes', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function maintenances(): HasMany
    {
        return $this->hasMany(AssetMaintenance::class);
    }
}
