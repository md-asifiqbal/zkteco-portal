<?php

namespace App\Jobs;

use App\Models\Device;
use App\Models\PunchLog;
use App\Services\ZKTeco\ZKTecoClient;
use App\Services\ZKTeco\ZKTecoParser;
use App\Services\ZKTeco\ZKTecoService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class SyncDeviceLogsJob implements ShouldQueue
{
    use Queueable;

    public int $deviceId;

    public function __construct(int $deviceId)
    {
        $this->deviceId = $deviceId;
    }

    public function handle(): void
    {
        $device = Device::find($this->deviceId);

        if (! $device) {
            return;
        }

        try {
            $client = new ZKTecoClient($device->ip_address, $device->port ?? 4370);
            $parser = new ZKTecoParser;

            $service = new ZKTecoService($client, $parser);
            $logs = $service->syncAttendance($device->id);

            Log::info('Device synced', [
                'device_id' => $device->id,
                'count' => count($logs),
            ]);

            $now = now();

            // 🔹 Get last timestamp from DB
            // $lastTimestamp = PunchLog::where('device_id', $this->device->id)
            //     ->max('timestamp');

            $logs = collect($logs);

            // 🔥 Filter + transform
            $payload = $logs
               // ->filter(fn ($log) => ! $lastTimestamp || $log['timestamp'] > $lastTimestamp)
                ->map(fn ($log) => [
                    'device_id' => $device->id,
                    'tenant_id' => $device->tenant_id,
                    'user_id' => $log['user_id'] ?? null,
                    'timestamp' => $log['timestamp'] ?? null,
                    'verify_type' => $log['verify_type'] ?? null,
                    'status' => $log['status'] ?? null,
                    'stamp' => $device->last_stamp ?? 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                    'source' => 2, // 1: push, 2: pull
                ])
                ->values()
                ->all();

            // 🚀 Single bulk upsert (FAST)
            if (! empty($payload)) {
                PunchLog::upsert(
                    $payload,
                    ['device_id', 'user_id', 'timestamp'], // unique keys
                    ['status', 'verify_type', 'stamp', 'updated_at', 'source']
                );
            }

        } catch (\Throwable $e) {

            Log::error('Device sync failed', [
                'device_id' => $device->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
