<?php

namespace App\Services\ZKTeco;

use Illuminate\Support\Facades\Log;

class ZKTecoService
{
    protected $client;

    protected $parser;

    public function __construct(ZKTecoClient $client, ZKTecoParser $parser)
    {
        $this->client = $client;
        $this->parser = $parser;
    }

    public function syncAttendance($deviceId)
    {
        $this->client->connect();

        $raw = $this->client->fetchAttendanceRaw();
        Log::info('Fetched raw attendance data', [
            'device_id' => $deviceId,
            'length' => strlen($raw),
            'hex_sample' => substr(bin2hex($raw), 0, 200),
        ]);
        $logs = $this->parser->parseAttendance($raw);

        $this->client->disconnect();

        return $logs;
    }
}
