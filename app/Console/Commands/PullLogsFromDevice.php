<?php

namespace App\Console\Commands;

use App\Models\Device;
use App\Services\ZKTeco\ZKTecoClient;
use App\Services\ZKTeco\ZKTecoParser;
use App\Services\ZKTeco\ZKTecoService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:pull-logs-from-device {device_id}')]
#[Description('Command description')]
class PullLogsFromDevice extends Command
{
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $device = Device::where('id', $this->argument('device_id'))->first();

        if (! $device) {
            $this->error('No device found');

            return;
        }

        $this->sync($device);
    }

    public function sync(Device $device)
    {
        dump($device->ip_address);
        $client = new ZKTecoClient($device->ip_address, 4370, 123456);
        $parser = new ZKTecoParser;

        $service = new ZKTecoService($client, $parser);

        $logs = $service->syncAttendance($device->id);

        foreach ($logs as $log) {

            dd($log);
        }

        dd($logs);

        return response()->json(['count' => count($logs)]);
    }
}
