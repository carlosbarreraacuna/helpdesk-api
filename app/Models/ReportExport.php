<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReportExport extends Model
{
    protected $fillable = [
        'user_id',
        'report_template_id',
        'file_path',
        'file_format',
        'filters',
        'exported_at',
    ];

    protected $casts = [
        'filters' => 'array',
        'exported_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reportTemplate()
    {
        return $this->belongsTo(ReportTemplate::class);
    }
}
