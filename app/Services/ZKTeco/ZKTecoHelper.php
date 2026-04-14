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
        $data = $this->buildUserPacket($uid, $userId, $name, $password, $role);

        return $this->execute(ZKTecoClient::CMD_USER_WRQ, $data);
    }

    protected function buildUserPacket($uid, $userId, $name, $password, $role)
    {
        // UID (2 bytes)
        $uidPack = pack('v', $uid);

        // Role (1 byte)
        $rolePack = chr($role);

        // Password (8 bytes)
        $passwordPack = str_pad(substr($password, 0, 8), 8, "\0");

        // Name (24 bytes)
        $namePack = str_pad(substr($name, 0, 24), 24, "\0");

        // User ID (9 bytes normally, but many devices accept 12–16)
        $userIdPack = str_pad(substr($userId, 0, 12), 12, "\0");

        // Padding to match 72 bytes total
        $padding = str_repeat("\0", 72 - (
            strlen($uidPack) +
            strlen($rolePack) +
            strlen($passwordPack) +
            strlen($namePack) +
            strlen($userIdPack)
        ));

        return $uidPack
            .$rolePack
            .$passwordPack
            .$namePack
            .$userIdPack
            .$padding;
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
