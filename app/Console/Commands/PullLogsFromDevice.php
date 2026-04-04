<?php

namespace App\Console\Commands;

use App\Jobs\SyncDeviceLogsJob;
use App\Models\Device;
use Illuminate\Console\Command;

class PullLogsFromDevice extends Command
{
    protected $signature = 'device:pull-logs {--offline}';

    protected $description = 'Dispatch log sync jobs for devices';

    public function handle(): int
    {
        $offline = $this->option('offline');

        $this->info('Starting device log sync...');

        Device::query()
            ->when(! $offline, function ($query) {
                $query->where('is_support_cloud', true);
            })
            ->chunk(5, function ($devices) {

                foreach ($devices as $device) {
                    dispatch(new SyncDeviceLogsJob($device->id));

                    $this->line("Queued device: {$device->ip_address}");
                }
            });

        $this->info('✅ All devices queued.');

        return Command::SUCCESS;
    }
}
