<?php

namespace App\Console\Commands;

use App\Models\Device;
use App\Services\ZKTeco\ZKTecoFactory;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:pull-attendence {--device= : Device ID to pull attendance from}')]
#[Description('Command description')]
class PullAttendence extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(ZKTecoFactory $factory): int
    {
        $deviceId = $this->option('device');

        if ($deviceId) {
            $device = Device::find($deviceId);
            if (! $device) {
                $this->error("Device with ID {$deviceId} not found.");

                return Command::FAILURE;
            }
            $service = $factory->make($device);
            $logs = $service->syncUsers();
            dd(collect($logs)->take(20)->toArray());
        }

        return Command::SUCCESS;
    }
}
