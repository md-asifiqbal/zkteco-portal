<?php

namespace App\Services\ZKTeco;

use Illuminate\Support\Facades\Log;

class ZKTecoService
{
    protected $client;

    protected $parser;

    protected $helper;

    protected $architecture;

    public function __construct(
        ZKTecoClient $client,
        ZKTecoParser $parser,
        ZKTecoHelper $helper,
        Architectures\ArchitectureInterface $architecture
    ) {
        $this->client = $client;
        $this->parser = $parser;
        $this->helper = $helper;
        $this->architecture = $architecture;
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

            if (empty($raw)) {
                $this->client->disconnect();
                return [];
            }

            $logs = $this->parser->parse($raw, $this->architecture);

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

    public function createUser($uid, $name, $role = 0)
    {
        try {
            $this->client->connect();

            $result = $this->helper->createUser($uid, $name, $role);

            $this->client->disconnect();

            return $result;

        } catch (\Throwable $e) {
            Log::error('ZK Create User Error', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
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

        $size = $this->architecture->getUserPacketSize();

        for ($i = 0; $i < strlen($data); $i += $size) {

            $chunk = substr($data, $i, $size);

            $parsed = $this->architecture->parseUserChunk($chunk);
            if ($parsed) {
                $users[] = $parsed;
            }
        }

        return $users;
    }
}
