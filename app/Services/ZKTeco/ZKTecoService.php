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

    public function createUser($uid, $userId, $name, $password = '', $role = 0)
    {
        try {
            $this->client->connect();

            $result = $this->helper->createUser($uid, $userId, $name, $password, $role);

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

        for ($i = 0; $i < strlen($data); $i += 72) {

            $chunk = substr($data, $i, 72);

            if (strlen($chunk) < 72) {
                continue;
            }

            $users[] = [
                'uid' => unpack('v', substr($chunk, 0, 2))[1] ?? null,

                // 🔥 FIXED
                'user_id' => $this->extractUserId($chunk),

                'name' => $this->cleanString(substr($chunk, 11, 24)),

                'role' => ord($chunk[35] ?? 0),

                'raw_hex' => bin2hex($chunk),
            ];
        }

        return $users;
    }

    protected function extractUserId($chunk)
    {
        $hex = bin2hex($chunk);

        // look for "SS-EMP-" pattern
        if (preg_match('/53532d454d502d[0-9]+/', $hex, $match)) {
            return hex2bin($match[0]);
        }

        return null;
    }

    protected function cleanString($value)
    {
        if (! $value) {
            return null;
        }

        // remove null bytes
        $value = str_replace("\0", '', $value);

        // remove non-printable characters
        $value = preg_replace('/[^\x20-\x7E]/', '', $value);

        // fix encoding issues
        $value = iconv('UTF-8', 'UTF-8//IGNORE', $value);

        return trim($value);
    }
}
