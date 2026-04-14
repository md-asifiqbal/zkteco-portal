<?php

namespace App\Console\Commands;

use App\Models\Device;
use App\Services\ZKTeco\ZKTecoFactory;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

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

            // $result = $service->createUser(6, 'SS-EMP-6', 'Mawdud Ahmed');
            // Log::info('Create user result', [
            //     'device_id' => $deviceId,
            //     'result' => $result,
            // ]);
            // if ($result) {
            //     Log::info('User created successfully', [
            //         'device_id' => $deviceId,
            //     ]);
            //     $this->info("User created successfully for device ID {$deviceId}.");
            // } else {
            //     $this->error("Failed to create user for device ID {$deviceId}.");
            // }

            $logs = $service->syncUsers();
            Log::info('Pulled attendance for device', [
                'device_id' => $deviceId,
                'logs_count' => count($logs),
                'users' => $logs,
            ]);
            $this->info("Pulled attendance for device ID {$deviceId}. Logs count: ".count($logs));
        }

        return Command::SUCCESS;
    }
}
