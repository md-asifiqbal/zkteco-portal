<?php

namespace App\Jobs;

use App\Models\Device;
use App\Models\PunchLog;
use App\Services\ZKTeco\ZKTecoFactory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SyncDeviceLogsJob implements ShouldQueue
{
    use Queueable;

    public int $deviceId;

    public $timeout = 60;

    public $tries = 3;

    public function __construct(int $deviceId)
    {
        $this->deviceId = $deviceId;
    }

    public function handle(ZKTecoFactory $factory): void
    {
        $device = Device::find($this->deviceId);

        if (! $device) {
            return;
        }

        // 🔒 Prevent duplicate processing
        $lock = Cache::lock("device-sync-{$device->id}", 30);

        if (! $lock->get()) {
            return;
        }

        try {
            $service = $factory->make($device);

            $logs = collect($service->syncAttendance($device->id));

            Log::info('Device synced', [
                'device_id' => $device->id,
                'count' => $logs->count(),
            ]);

            // 🔹 Get last timestamp
            $lastTimestamp = PunchLog::where('device_id', $device->id)
                ->max('timestamp');

            $now = now();

            // 🔥 Clean + filter + transform
            $payload = $logs
                ->filter(fn ($log) => isset($log['timestamp']) &&
                    (! $lastTimestamp || $log['timestamp'] > $lastTimestamp)
                )
                ->map(function ($log) use ($device, $now) {

                    return [
                        'device_id' => $device->id,
                        'tenant_id' => $device->tenant_id,
                        'user_id' => $this->safe($log['user_id'] ?? null),
                        'timestamp' => $log['timestamp'],
                        'verify_type' => $log['verify_type'] ?? null,
                        'status' => $log['status'] ?? null,
                        'stamp' => $device->last_stamp ?? 0,
                        'created_at' => $now,
                        'updated_at' => $now,
                        'source' => 2,
                    ];
                })
                ->sortBy('timestamp')
                ->values();

            // 🚀 Chunk + upsert
            $payload->chunk(500)->each(function ($chunk) {
                PunchLog::upsert(
                    $chunk->toArray(),
                    ['device_id', 'user_id', 'timestamp'],
                    ['status', 'verify_type', 'stamp', 'updated_at', 'source']
                );
            });

        } catch (\Throwable $e) {

            Log::error('Device sync failed', [
                'device_id' => $device->id,
                'error' => $e->getMessage(),
            ]);

        } finally {
            optional($lock)->release();
        }
    }

    // 🔥 UTF-8 safe helper
    private function safe($value)
    {
        if (! is_string($value)) {
            return $value;
        }

        // Remove invalid UTF-8 characters
        $clean = iconv('UTF-8', 'UTF-8//IGNORE', $value);

        // Remove weird symbols (optional)
        return preg_replace('/[^\x20-\x7E]/', '', $clean);
    }
}
