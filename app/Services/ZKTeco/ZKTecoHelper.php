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

        // Command 8 (CMD_USER_WRQ) is the standard ZKTeco protocol for writing a user payload directly.
        // The PREPARE_DATA sequence is generally for very large templates (like images), not 72-byte structs.
        $this->client->send(8, $packet);
        $response = $this->client->receive();

        if (empty($response) || strlen($response) < 8) {
            throw new \Exception('User creation failed: No valid response from device.');
        }

        $header = unpack('vcommand/vchecksum/vsession/vreply', substr($response, 0, 8));

        // 2000 is CMD_ACK
        if ($header['command'] != 2000) {
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
