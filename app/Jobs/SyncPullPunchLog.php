<?php

namespace App\Jobs;

use App\Models\PunchLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncPullPunchLog implements ShouldQueue
{
    use Queueable;

    public function __construct(public $device, public $logs)
    {
        //
    }

    public function handle(): void
    {
        $now = now();

        // 🔹 Get last timestamp from DB
        // $lastTimestamp = PunchLog::where('device_id', $this->device->id)
        //     ->max('timestamp');

        $logs = collect($this->logs);

        // 🔥 Filter + transform
        $payload = $logs
           // ->filter(fn ($log) => ! $lastTimestamp || $log['timestamp'] > $lastTimestamp)
            ->map(fn ($log) => [
                'device_id' => $this->device->id,
                'tenant_id' => $this->device->tenant_id,
                'user_id' => $log['user_id'] ?? null,
                'timestamp' => $log['timestamp'] ?? null,
                'verify_type' => $log['verify_type'] ?? null,
                'status' => $log['status'] ?? null,
                'stamp' => $this->device->last_stamp ?? 0,
                'created_at' => $now,
                'updated_at' => $now,
            ])
            ->values()
            ->all();

        // 🚀 Single bulk upsert (FAST)
        if (! empty($payload)) {
            PunchLog::upsert(
                $payload,
                ['device_id', 'user_id', 'timestamp'], // unique keys
                ['status', 'verify_type', 'stamp', 'updated_at']
            );
        }
    }
}
