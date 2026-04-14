<?php

namespace App\Services\ZKTeco;

use Illuminate\Support\Facades\Log;

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
        // ✅ safest SSR format
        $data = "PIN={$userId}\tName={$name}\tPri={$role}\tPasswd={$password}\tCard=0";

        // ✅ VERY IMPORTANT: add null terminator
        $data .= "\0";

        $this->client->send(ZKTecoClient::CMD_USER_WRQ, $data);

        $response = $this->client->receive();

        if (! $response || strlen($response) < 8) {
            throw new \Exception('No response from device');
        }

        $header = unpack('vcommand/vchecksum/vsession/vreply', substr($response, 0, 8));

        // DEBUG
        Log::info('ZK create user response', $header);

        if ($header['command'] != ZKTecoClient::CMD_ACK) {
            throw new \Exception('User creation failed: '.$header['command']);
        }

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
