<?php

namespace App\Services\Assets;

use App\Models\Asset;
use App\Models\AssetEvent;

class AssetEventService
{
    public function record(Asset $asset, string $eventType, string $description, int $performedBy, array $metadata = []): AssetEvent
    {
        return AssetEvent::create([
            'asset_id'     => $asset->id,
            'event_type'   => $eventType,
            'description'  => $description,
            'performed_by' => $performedBy,
            'metadata'     => $metadata ?: null,
            'occurred_at'  => now(),
        ]);
    }
}
