<?php

namespace App\Http\Controllers\Devices;

use App\Http\Controllers\Controller;
use App\Jobs\ZkTecos\ProcessZKPushJob;
use App\Models\Device;
use Illuminate\Http\Request;

class ZKPushController extends Controller
{
    public function handle(Request $request)
    {
        $sn = $request->query('SN');

        info("Received push from SN: {$sn}", [
            'query' => $request->query(),
            'content' => $request->getContent(),
        ]);

        $device = Device::where('serial_number', $sn)->first();

        if (! $device) {
            return response('DEVICE NOT FOUND', 404);
        }

        // Bind tenant automatically
        app()->instance('tenant', $device->tenant);

        if ($request->isMethod('get')) {
            return $this->handshake($device);
        }

        $table = $request->query('table');
        $content = trim($request->getContent());

        if ($table === 'ATTLOG' && ! empty($content)) {
            dispatch(new ProcessZKPushJob(
                $device->id,
                $device->tenant_id,
                $request->getContent(),
                (int) $request->query('Stamp', 0)
            ));
        }

        return response('OK');
    }

    private function handshake($device)
    {
        $device->update(['last_heartbeat' => now()]);

        $opStamp = $device->last_op_stamp ?? $device->last_stamp;

        $options = [
            "GET OPTION FROM: SN={$device->serial_number}",
            "Stamp={$device->last_stamp}",
            "OpStamp={$opStamp}",
            'ErrorDelay=30',
            'Delay=10',
            'TransInterval=1',
            'TransFlag=1111000000',
            'Realtime=1',
            'TransTimes=00:00;23:59',
        ];

        // FIX: Use & to join parameters for ADMS protocol compatibility
        return response(implode('&', $options))
            ->header('Content-Type', 'text/plain');
    }
}
