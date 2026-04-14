<?php

namespace App\Services\ZKTeco;

class ZKTecoHelper
{
    protected $client;
    protected $architecture;

    public function __construct(ZKTecoClient $client, Architectures\ArchitectureInterface $architecture)
    {
        $this->client = $client;
        $this->architecture = $architecture;
    }

    /*
    |--------------------------------------------------------------------------
    | CORE EXECUTOR
    |--------------------------------------------------------------------------
    */

    public function execute($command, $data = '')
    {
        $this->client->send($command, $data);

        $response = $this->client->receive();

        if (! $response || strlen($response) < 8) {
            return null;
        }

        $header = unpack('vcommand/vchecksum/vsession/vreply', substr($response, 0, 8));

        if ($header['command'] == ZKTecoClient::CMD_PREPARE_DATA) {
            return $this->readMultiPacket();
        }

        return substr($response, 8);
    }

    protected function readMultiPacket()
    {
        $data = '';

        while (true) {
            $res = $this->client->receive();

            if (! $res || strlen($res) < 8) {
                break;
            }

            $header = unpack('vcommand/vchecksum/vsession/vreply', substr($res, 0, 8));
            $body = substr($res, 8);

            if ($header['command'] == ZKTecoClient::CMD_DATA) {
                $data .= $body;
            }

            if ($header['command'] == ZKTecoClient::CMD_FREE_DATA) {
                break;
            }
        }

        return $data;
    }

    /*
    |--------------------------------------------------------------------------
    | ATTENDANCE
    |--------------------------------------------------------------------------
    */

    public function fetchAttendance()
    {
        return $this->execute(ZKTecoClient::CMD_ATTLOG_RRQ);
    }

    /*
    |--------------------------------------------------------------------------
    | USERS
    |--------------------------------------------------------------------------
    */

    public function fetchUsers()
    {
        return $this->execute(ZKTecoClient::CMD_USER_RRQ);
    }

    public function createUser($uid, $name, $role = 0)
    {
        $packet = $this->architecture->buildUserPacket($uid, $name, $role);
        $packetSize = strlen($packet); // Dynamic based on architecture

        // 1. PREPARE - Tell device how much data is coming
        $this->client->send(ZKTecoClient::CMD_PREPARE_DATA, pack('V', $packetSize));
        $this->client->receive();

        // 2. DATA - Send the actual 72 bytes
        $this->client->send(ZKTecoClient::CMD_DATA, $packet);
        $this->client->receive();

        // 3. WRITE - Tell device to commit the data to flash memory
        $this->client->send(ZKTecoClient::CMD_USER_WRQ);
        $response = $this->client->receive();

        $header = unpack('vcommand/vchecksum/vsession/vreply', substr($response, 0, 8));

        if ($header['command'] != ZKTecoClient::CMD_ACK) {
            throw new \Exception('User creation failed: '.$header['command']);
        }

        return true;

    }


    /*
    |--------------------------------------------------------------------------
    | DEVICE
    |--------------------------------------------------------------------------
    */

    public function getDeviceInfo()
    {
        return $this->execute(ZKTecoClient::CMD_DEVICE);
    }

    public function getSSR()
    {
        return $this->execute(ZKTecoClient::CMD_DEVICE, '~SSR');
    }

    public function clearAttendance()
    {
        return $this->execute(ZKTecoClient::CMD_CLEAR_ATTLOG);
    }

    public function restart()
    {
        return $this->execute(ZKTecoClient::CMD_RESTART);
    }
}
