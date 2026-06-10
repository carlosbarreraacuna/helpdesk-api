<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SlaConfig extends Model
{
    protected $fillable = [
        'priority',
        'response_time_hours',
        'resolution_time_hours',
        'alert_threshold',
        'work_start_hour',
        'work_end_hour',
    ];

    protected $casts = [
        'response_time_hours'   => 'integer',
        'resolution_time_hours' => 'integer',
        'alert_threshold'       => 'integer',
        'work_start_hour'       => 'integer',
        'work_end_hour'         => 'integer',
    ];

    public static function forPriority(string $priority): ?self
    {
        return static::where('priority', $priority)->first();
    }
}
