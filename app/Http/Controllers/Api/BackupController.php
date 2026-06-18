<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\RestoreDatabaseBackupJob;
use App\Models\BackupLog;
use App\Services\BackupService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * @tags Respaldos
 */
class BackupController extends Controller
{
    public function index(Request $request)
    {
        $query = BackupLog::where('type', 'backup');

        if ($request->status && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        return response()->json(
            $query->orderByDesc('created_at')->paginate(20)
        );
    }

    public function show($id)
    {
        return response()->json(BackupLog::where('type', 'backup')->findOrFail($id));
    }

    public function download($id)
    {
        $backup = BackupLog::where('type', 'backup')
            ->where('status', 'success')
            ->findOrFail($id);

        $url = Storage::disk('s3')->temporaryUrl(
            $backup->db_dump_path,
            now()->addMinutes(10)
        );

        return response()->json(['url' => $url]);
    }

    public function restoreHistory(Request $request)
    {
        return response()->json(
            BackupLog::where('type', 'restore')
                ->with(['triggeredBy:id,name,email', 'sourceBackup:id,timestamp_key'])
                ->orderByDesc('created_at')
                ->paginate(20)
        );
    }

    public function restoreStatus($id)
    {
        $restore = BackupLog::where('type', 'restore')->findOrFail($id);

        return response()->json([
            'id'            => $restore->id,
            'status'        => $restore->status,
            'error_message' => $restore->error_message,
            'started_at'    => $restore->started_at,
            'finished_at'   => $restore->finished_at,
        ]);
    }

    public function restore(Request $request, $id, BackupService $service)
    {
        $request->validate([
            'confirmation' => 'required|in:RESTAURAR',
        ]);

        $backup = BackupLog::where('type', 'backup')
            ->where('status', 'success')
            ->findOrFail($id);

        $restore = $service->createPendingRestore($backup, $request->user()->id);

        RestoreDatabaseBackupJob::dispatch($restore);

        return response()->json([
            'message'    => 'Restauración encolada.',
            'restore_id' => $restore->id,
        ], 202);
    }
}
