<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class AssetMaintenance extends Model
{
    protected $fillable = [
        'asset_id', 'maintenance_type', 'status', 'description',
        'service_provider_id', 'scheduled_date', 'executed_date',
        'cost', 'invoice_number', 'notes', 'created_by',
    ];

    protected $casts = [
        'scheduled_date' => 'date',
        'executed_date'  => 'date',
        'cost'           => 'decimal:2',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function serviceProvider(): BelongsTo
    {
        return $this->belongsTo(ServiceProvider::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function tickets(): BelongsToMany
    {
        return $this->belongsToMany(Ticket::class, 'asset_maintenance_tickets');
    }
}
