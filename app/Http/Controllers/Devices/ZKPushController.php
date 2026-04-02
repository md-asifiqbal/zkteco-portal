<?php

namespace App\Http\Controllers\Devices;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ZKPushController extends Controller
{
    public function handle(Request $request)
    {
        Log::info('ZK Request', [
            'method' => $request->method(),
            'query' => $request->query(),
            'body' => $request->getContent(),
            'data' => $request->all(),
        ]);

        if ($request->query('table') === 'ATTLOG') {

            $lines = explode("\n", trim($request->getContent()));

            foreach ($lines as $line) {

                $cols = explode("\t", $line);

                if (count($cols) < 3) {
                    continue;
                }

                $userId = $cols[0];
                $verifyMode = $cols[1];
                $timestamp = $cols[2];
                $status = $cols[3] ?? 0;

                Log::info('PUNCH', [
                    'user_id' => $userId,
                    'time' => $timestamp,
                    'status' => $status,
                ]);
            }

            return response('OK');
        }
    }
}
