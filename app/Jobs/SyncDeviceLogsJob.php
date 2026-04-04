<?php

namespace App\Jobs;

use App\Models\Device;
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
            $logs = $service->syncAttendance($device);

            Log::info('Device synced', [
                'device_id' => $device->id,
                'count' => count($logs),
            ]);

            dispatch(new SyncPullPunchLog($device, $logs));

        } catch (\Throwable $e) {

            Log::error('Device sync failed', [
                'device_id' => $device->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
