<?php

namespace App\Services\ZKTeco;

class ZKTecoClient
{
    public $ip;

    public $port;

    public $socket;

    public $session_id = 0;

    public $reply_id = 0;

    const CMD_CONNECT = 1000;

    const CMD_EXIT = 1001;

    const CMD_ATTLOG_RRQ = 13;

    const CMD_PREPARE_DATA = 1500;

    const CMD_DATA = 1501;

    const CMD_FREE_DATA = 1502;

    const CMD_ACK = 2000;

    const CMD_USER_RRQ = 9;

    const CMD_DEVICE = 11;

    const CMD_CLEAR_ATTLOG = 14;

    const CMD_RESTART = 1003;

    const CMD_USER_WRQ = 10;

    public function __construct($ip, $port = 4370)
    {
        $this->ip = $ip;
        $this->port = $port;
    }

    /**
     * Calculate the 16-bit Checksum required by ZK protocol
     */
    public function createChecksum($payload)
    {
        $acc = 0;
        if (strlen($payload) % 2 != 0) {
            $payload .= "\x00";
        }

        $words = unpack('v*', $payload);

        foreach ($words as $word) {
            $acc += $word;
            if ($acc > 0xFFFF) {
                $acc -= 0xFFFF;
            }
        }

        return ~$acc & 0xFFFF;
    }

    public function connect()
    {
        // Use UDP (udp://) as it is the default for the ZK binary protocol
        $this->socket = fsockopen('udp://'.$this->ip, $this->port, $errno, $errstr, 5);

        if (! $this->socket) {
            throw new \Exception("ZKTeco Connection failed: $errstr ($errno)");
        }

        stream_set_timeout($this->socket, 5);

        $this->send(self::CMD_CONNECT);
        $res = $this->receive();

        if (empty($res) || strlen($res) < 8) {
            throw new \Exception('No response from device. Check if Port 4370 is open or if a Comm Key (Password) is set on the device.');
        }

        $header = unpack('vcommand/vchecksum/vsession/vreply', substr($res, 0, 8));
        $this->session_id = $header['session'];

        return true;
    }

    public function disconnect()
    {
        $this->send(self::CMD_EXIT);
        if ($this->socket) {
            fclose($this->socket);
        }
    }

    public function send($command, $data = '')
    {
        // Increment reply ID for every packet sent
        $this->reply_id++;
        if ($this->reply_id >= 0xFFFF) {
            $this->reply_id = 0;
        }

        // 1. Pack with 0 checksum to calculate the real one
        $buf = pack('vvvv', $command, 0, $this->session_id, $this->reply_id).$data;

        // 2. Calculate Checksum
        $checksum = $this->createChecksum($buf);

        // 3. Final Packet
        $packet = pack('vvvv', $command, $checksum, $this->session_id, $this->reply_id).$data;

        fwrite($this->socket, $packet);
    }

    public function receive()
    {
        $data = fread($this->socket, 65535);

        return $data;
    }

    public function fetchAttendanceRaw()
    {
        $this->send(self::CMD_ATTLOG_RRQ);
        $response = $this->receive();

        if (empty($response) || strlen($response) < 8) {
            return null;
        }

        $header = unpack('vcommand/vchecksum/vsession/vreply', substr($response, 0, 8));

        if ($header['command'] == self::CMD_PREPARE_DATA) {
            return $this->readMultiPacket();
        }

        return substr($response, 8);
    }

    public function readMultiPacket()
    {
        $data = '';

        while (true) {
            $res = $this->receive();
            if (! $res || strlen($res) < 8) {
                break;
            }

            $header = unpack('vcommand/vchecksum/vsession/vreply', substr($res, 0, 8));
            $body = substr($res, 8);

            if ($header['command'] == self::CMD_DATA) {
                $data .= $body;
            }

            // Once we get FREE_DATA, the device is done sending the log buffer
            if ($header['command'] == self::CMD_FREE_DATA || $header['command'] == self::CMD_ACK) {
                break;
            }
        }

        return $data;
    }
}
