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

    public function createUser($uid, $userId, $name, $password = '', $role = 0)
    {
        $packet = $this->buildUserPacket($uid, $userId, $name, $password, $role);

        // 1️⃣ Tell device we will send data
        $this->client->send(ZKTecoClient::CMD_PREPARE_DATA, pack('V', strlen($packet)));
        $this->client->receive();

        // 2️⃣ Send actual data
        $this->client->send(ZKTecoClient::CMD_DATA, $packet);
        $this->client->receive();

        // 3️⃣ Final write command
        $this->client->send(ZKTecoClient::CMD_USER_WRQ);
        $this->client->receive();

        // 4️⃣ Free buffer
        $this->client->send(ZKTecoClient::CMD_FREE_DATA);
        $this->client->receive();

        return true;
    }

    protected function buildUserPacket($uid, $userId, $name, $password, $role)
    {
        return pack(
            'vZ8Z24Z8Z16',
            $uid,           // UID
            $userId,        // User ID (8 bytes typical)
            $name,          // Name (24)
            $password,      // Password (8)
            ''              // Card / padding
        ).chr($role);     // Role at end
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
