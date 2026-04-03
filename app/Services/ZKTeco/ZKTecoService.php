<?php

namespace App\Services\ZKTeco;

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
        $logs = $this->parser->parseAttendance($raw);

        $this->client->disconnect();

        return $logs;
    }
}
