<?php

namespace App\Services\ZKTeco;

use Illuminate\Support\Facades\Log;

class ZKTecoService
{
    protected $client;

    protected $parser;

    protected $helper;

    public function __construct(
        ZKTecoClient $client,
        ZKTecoParser $parser,
        ZKTecoHelper $helper
    ) {
        $this->client = $client;
        $this->parser = $parser;
        $this->helper = $helper;
    }

    /*
    |--------------------------------------------------------------------------
    | ATTENDANCE SYNC
    |--------------------------------------------------------------------------
    */

    public function syncAttendance($deviceId)
    {
        try {
            $this->client->connect();

            $raw = $this->helper->fetchAttendance();

            $logs = $this->parser->parse($raw);

            $this->client->disconnect();

            return $logs;

        } catch (\Throwable $e) {

            Log::error('ZK Error', [
                'device_id' => $deviceId,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /*
    |--------------------------------------------------------------------------
    | USER SYNC
    |--------------------------------------------------------------------------
    */

    public function syncUsers()
    {
        $this->client->connect();

        $raw = $this->helper->fetchUsers();

        $this->client->disconnect();

        return $this->parseUsers($raw);
    }

    /*
    |--------------------------------------------------------------------------
    | DEVICE COMMANDS
    |--------------------------------------------------------------------------
    */

    public function deviceInfo()
    {
        $this->client->connect();
        $res = $this->helper->getDeviceInfo();
        $this->client->disconnect();

        return $res;
    }

    public function checkSSR()
    {
        $this->client->connect();
        $res = $this->helper->getSSR();
        $this->client->disconnect();

        return $res;
    }

    public function clearLogs()
    {
        $this->client->connect();
        $res = $this->helper->clearAttendance();
        $this->client->disconnect();

        return $res;
    }

    /*
    |--------------------------------------------------------------------------
    | NORMALIZATION
    |--------------------------------------------------------------------------
    */

    protected function normalize($deviceId, $logs)
    {
        return collect($logs)->map(function ($row) use ($deviceId) {
            return [
                'device_id' => $deviceId,
                'uid' => $row['uid'] ?? null,
                'user_id' => $row['user_id'] ?? null,
                'timestamp' => optional($row['timestamp'])->toDateTimeString(),
                'status' => $row['status'] ?? 0,
                'verify_type' => $row['verify_type'] ?? 0,
                'stamp' => $row['stamp'] ?? null,
                'raw' => $row['raw_hex'] ?? null,
            ];
        })->toArray();
    }

    /*
    |--------------------------------------------------------------------------
    | USER PARSER (GENERIC)
    |--------------------------------------------------------------------------
    */

    protected function parseUsers($data)
    {
        $users = [];

        if (! $data) {
            return [];
        }

        for ($i = 0; $i < strlen($data); $i += 72) {

            $chunk = substr($data, $i, 72);

            if (strlen($chunk) < 72) {
                continue;
            }

            $users[] = [
                'uid' => unpack('v', substr($chunk, 0, 2))[1] ?? null,
                'user_id' => trim(str_replace("\0", '', substr($chunk, 2, 9))),
                'name' => trim(str_replace("\0", '', substr($chunk, 11, 24))),
                'role' => ord($chunk[35] ?? 0),
                'raw_hex' => bin2hex($chunk),
            ];
        }

        return $users;
    }
}
