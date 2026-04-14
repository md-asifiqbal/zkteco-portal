<?php

namespace App\Services\ZKTeco;

use App\Services\ZKTeco\Architectures\Standard;
use App\Services\ZKTeco\Architectures\VisibleLight;

class ZKTecoFactory
{
    public function make($device): ZKTecoService
    {
        $client = new ZKTecoClient($device->ip_address, $device->port ?? 4370);

        $architecture = strtolower($device->architecture ?? 'standard') === 'vl'
            ? new VisibleLight()
            : new Standard();

        $parser = new ZKTecoParser;

        $helper = new ZKTecoHelper($client, $architecture);

        return new ZKTecoService($client, $parser, $helper, $architecture);
    }
}
