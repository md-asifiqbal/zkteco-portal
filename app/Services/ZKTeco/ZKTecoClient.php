<?php

namespace App\Services\ZKTeco;

class ZKTecoClient
{
    protected $ip;

    protected $port;

    protected $socket;

    protected $session_id = 0;

    protected $reply_id = 0;

    const CMD_CONNECT = 1000;

    const CMD_EXIT = 1001;

    const CMD_ATTLOG_RRQ = 13;

    const CMD_PREPARE_DATA = 1500;

    const CMD_DATA = 1501;

    const CMD_FREE_DATA = 1502;

    public function __construct($ip, $port = 4370)
    {
        $this->ip = $ip;
        $this->port = $port;
    }

    // 🔌 CONNECT
    public function connect()
    {
        $this->socket = fsockopen($this->ip, $this->port, $errno, $errstr, 5);

        if (! $this->socket) {
            throw new \Exception("Connection failed: $errstr ($errno)");
        }

        stream_set_timeout($this->socket, 5);

        $this->send(self::CMD_CONNECT);

        $res = $this->receive();

        if (! $res || strlen($res) < 8) {
            throw new \Exception('No valid response from device (check protocol/checksum)');
        }

        $header = unpack('vcommand/vchecksum/vsession/vreply', substr($res, 0, 8));

        $this->session_id = $header['session'];
    }

    // 🔌 DISCONNECT
    public function disconnect()
    {
        $this->send(self::CMD_EXIT);
        fclose($this->socket);
    }

    // 📤 SEND WITH CHECKSUM
    protected function send($command, $data = '')
    {
        $this->reply_id++;

        // initial packet
        $packet = pack('vvvv', $command, 0, $this->session_id, $this->reply_id).$data;

        // calculate checksum
        $checksum = $this->checksum($packet);

        // rebuild packet with checksum
        $packet = pack('vvvv', $command, $checksum, $this->session_id, $this->reply_id).$data;

        fwrite($this->socket, $packet);
    }

    // 🔢 CHECKSUM FUNCTION (CRITICAL)
    protected function checksum($packet)
    {
        $chksum = 0;
        $length = strlen($packet);

        for ($i = 0; $i < $length; $i += 2) {
            $chksum += ord($packet[$i]) + ((ord($packet[$i + 1] ?? 0)) << 8);
            $chksum = $chksum & 0xFFFF;
        }

        $chksum = (~$chksum + 1) & 0xFFFF;

        return $chksum;
    }

    // 📥 RECEIVE
    protected function receive()
    {
        $data = '';

        while (! feof($this->socket)) {
            $chunk = fread($this->socket, 8192);

            if ($chunk === false || $chunk === '') {
                break;
            }

            $data .= $chunk;

            if (strlen($chunk) < 8192) {
                break;
            }
        }

        return $data;
    }

    // 📊 FETCH ATTENDANCE
    public function fetchAttendanceRaw()
    {
        $this->send(self::CMD_ATTLOG_RRQ);

        $response = $this->receive();

        if (! $response || strlen($response) < 8) {
            throw new \Exception('No attendance response from device');
        }

        $header = unpack('vcommand/vchecksum/vsession/vreply', substr($response, 0, 8));

        if ($header['command'] == self::CMD_PREPARE_DATA) {
            return $this->readMultiPacket();
        }

        return substr($response, 8);
    }

    // 📦 MULTI PACKET
    protected function readMultiPacket()
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

            if ($header['command'] == self::CMD_FREE_DATA) {
                break;
            }
        }

        return $data;
    }
}
