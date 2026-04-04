<?php

namespace App\Services\ZKTeco;

class ZKTecoFactory
{
    public function make($device): ZKTecoService
    {
        $client = new ZKTecoClient($device->ip_address, $device->port ?? 4370);

        $parser = new ZKTecoParser;

        $helper = new ZKTecoHelper($client);

        return new ZKTecoService($client, $parser, $helper);
    }
}
