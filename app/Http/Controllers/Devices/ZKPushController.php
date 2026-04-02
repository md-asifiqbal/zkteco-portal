<?php

namespace App\Http\Controllers\Devices;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ZKPushController extends Controller
{
    public function handle(Request $request, $path = null)
    {
        $method = $request->method();
        $sn = $request->query('SN');

        // 1. Log the incoming request for debugging
        Log::info("ZK $method Request", [
            'sn' => $sn,
            'query' => $request->query(),
            'body' => $request->getContent(),
        ]);

        // 2. Handle Handshake / Configuration (GET Request)
        if ($method === 'GET') {
            /**
             * The device is asking for instructions.
             * We must respond with this specific string to trigger the data push.
             */
            $config = "GET OPTION FROM: SN=$sn&Stamp=0&OpStamp=0&PhotoStamp=0&TransFlag=1111000000&ErrorDelay=30&Delay=10&TransInterval=1&TransTimes=00:00;23:59";

            return response($config)->header('Content-Type', 'text/plain');
        }

        // 3. Handle Data Upload (POST Request)
        if ($method === 'POST') {
            $table = $request->query('table');
            $content = trim($request->getContent());

            // Check if this POST contains Attendance Logs
            if ($table === 'ATTLOG' && !empty($content)) {
                $lines = explode("\n", $content);

                foreach ($lines as $line) {
                    // Columns are separated by Tabs (\t)
                    $cols = explode("\t", trim($line));

                    if (count($cols) >= 2) {
                        /**
                         * MB10-VL Standard Log Format:
                         * $cols[0] = User ID
                         * $cols[1] = Timestamp (YYYY-MM-DD HH:MM:SS)
                         * $cols[2] = Verify Mode (Face/Finger/Card)
                         * $cols[3] = Status (In/Out/Break)
                         */
                        $userId    = $cols[0];
                        $timestamp = $cols[1];
                        $status    = $cols[3] ?? '0';

                        Log::info('PUNCH RECEIVED', [
                            'sn'      => $sn,
                            'user_id' => $userId,
                            'time'    => $timestamp,
                            'status'  => $status,
                        ]);

                        // TODO: Save to your database here
                        // Attendance::updateOrCreate([...]);
                    }
                }
            }

            /**
             * CRITICAL: Always return 'OK' (uppercase) for POST requests.
             * If the device doesn't see 'OK', it will send the same data again and again.
             */
            return response("OK")->header('Content-Type', 'text/plain');
        }

        return response("OK")->header('Content-Type', 'text/plain');
    }
}
