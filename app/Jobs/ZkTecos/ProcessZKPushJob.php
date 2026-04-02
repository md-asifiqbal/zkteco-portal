<?php

namespace App\Jobs\ZkTecos;

use App\Models\Device;
use App\Models\PunchLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessZKPushJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $deviceId,
        public int $tenantId,
        public string $content,
        public int $stamp
    ) {}

    public function handle()
    {
        $lines = explode("\n", trim($this->content));
        $data = [];

        foreach ($lines as $line) {
            $cols = explode("\t", trim($line));

            if (count($cols) < 2) {
                continue;
            }

            // Ensure we have a valid timestamp format
            $data[] = [
                'tenant_id' => $this->tenantId,
                'device_id' => $this->deviceId,
                'user_id' => $cols[0],
                'timestamp' => $cols[1],
                'stamp' => $this->stamp,
                'verify_type' => $cols[2] ?? '0',
                'status' => $cols[3] ?? '0',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if (empty($data)) {
            return;
        }

        // FIX: Specify the columns to update in the 3rd argument of upsert
        collect($data)->chunk(500)->each(function ($chunk) {
            PunchLog::upsert(
                $chunk->toArray(),
                ['device_id', 'user_id', 'timestamp'], // Unique keys
                ['status', 'verify_type', 'stamp', 'updated_at'] // Columns to update on conflict
            );
        });

        // FIX: Only update device stamp if the incoming stamp is newer
        Device::where('id', $this->deviceId)
            ->where('last_stamp', '<', $this->stamp)
            ->update(['last_stamp' => $this->stamp]);
    }
}
