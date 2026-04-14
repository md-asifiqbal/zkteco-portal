<?php

namespace App\Services\ZKTeco;

class ZKTecoHelper
{
    protected $client;

    public function __construct(ZKTecoClient $client)
    {
        $this->client = $client;
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
        $packet = $this->buildUserPacket($uid, $name, $role);

        // PREPARE
        $this->client->send(ZKTecoClient::CMD_PREPARE_DATA, pack('V', strlen($packet)));
        $this->client->receive();

        // DATA
        $this->client->send(ZKTecoClient::CMD_DATA, $packet);
        $this->client->receive();

        // WRITE
        $this->client->send(ZKTecoClient::CMD_USER_WRQ);
        $response = $this->client->receive();

        $header = unpack('vcommand/vchecksum/vsession/vreply', substr($response, 0, 8));

        if ($header['command'] != ZKTecoClient::CMD_ACK) {
            throw new \Exception('User creation failed: '.$header['command']);
        }

        return true;
    }

    protected function buildUserPacket($uid, $name, $role = 0)
    {
        return pack(
            'v', $uid              // UID (2 bytes)
        )
        .str_repeat("\0", 8)     // unknown padding
        .chr($role)              // role
        .str_repeat("\0", 2)     // padding
        .str_pad(substr($name, 0, 24), 24, "\0") // name
        .str_repeat("\0", 3);    // tail padding
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
