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
        $packetSize = strlen($packet); // Should be 72

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

    protected function buildUserPacket($uid, $name, $role = 0)
    {
        // Initialize a 72-byte buffer of null bytes
        $packet = str_repeat("\x00", 72);

        // 1. UID (2 bytes, Offset 0)
        $uidBin = pack('v', $uid);
        $packet[0] = $uidBin[0];
        $packet[1] = $uidBin[1];

        // 2. Role (1 byte, Offset 2) - Note: Some firmwares use offset 2, others 35
        $packet[2] = chr($role);

        // 3. Name (Offset 11, length 24)
        $nameBin = str_pad(substr($name, 0, 24), 24, "\x00");
        for ($i = 0; $i < 24; $i++) {
            $packet[11 + $i] = $nameBin[$i];
        }

        // 4. User ID / Employee ID (Offset 40 or similar based on your hex)
        // Looking at your log: "985d500001000001000000000031..."
        // It seems your User ID "1" is at a specific offset.
        $userId = (string) $uid; // Or pass a specific ID
        $userIdBin = str_pad($userId, 9, "\x00"); // Standard length is 9 for many 72-byte devices

        // Attempting to place User ID at offset 48 (common for this 72-byte variant)
        for ($i = 0; $i < 9; $i++) {
            $packet[48 + $i] = $userIdBin[$i];
        }

        return $packet;
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
