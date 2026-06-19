<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SlaOverride extends Model
{
    protected $fillable = [
        'scope',
        'scope_id',
        'priority',
        'response_time_hours',
        'resolution_time_hours',
        'alert_threshold',
        'work_start_hour',
        'work_end_hour',
    ];

    protected $casts = [
        'scope_id'               => 'integer',
        'response_time_hours'   => 'integer',
        'resolution_time_hours' => 'integer',
        'alert_threshold'       => 'integer',
        'work_start_hour'       => 'integer',
        'work_end_hour'         => 'integer',
    ];

    public static function forScope(string $scope, int $scopeId, string $priority): ?self
    {
        return static::where('scope', $scope)
            ->where('scope_id', $scopeId)
            ->where('priority', $priority)
            ->first();
    }
}
