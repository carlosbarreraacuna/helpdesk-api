<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BackupLog extends Model
{
    protected $fillable = [
        'type', 'status', 'timestamp_key',
        'db_dump_path', 'manifest_path',
        'db_dump_size_bytes', 'attachments_object_count',
        'triggered_by', 'source_backup_id',
        'error_message', 'started_at', 'finished_at', 'purged_at',
    ];

    protected $casts = [
        'started_at'  => 'datetime',
        'finished_at' => 'datetime',
        'purged_at'   => 'datetime',
    ];

    public function triggeredBy()
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }

    public function sourceBackup()
    {
        return $this->belongsTo(BackupLog::class, 'source_backup_id');
    }
}
