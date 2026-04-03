<?php

namespace App\Services\ZKTeco;

class ZKTecoClient
{
    protected $ip;

    protected $port;

    protected $socket;

    protected $session_id = 0;

    protected $reply_id = 0;

    protected $comm_key;

    const CMD_CONNECT = 1000;

    const CMD_EXIT = 1001;

    const CMD_ATTLOG_RRQ = 13;

    const CMD_PREPARE_DATA = 1500;

    const CMD_DATA = 1501;

    const CMD_FREE_DATA = 1502;

    const CMD_ACK = 2000;

    public function __construct($ip, $port = 4370, $comm_key = 0)
    {
        $this->ip = $ip;
        $this->port = $port;
        $this->comm_key = $comm_key;
    }

    // 🔢 CHECKSUM
    protected function checksum($packet)
    {
        $chksum = 0;
        $length = strlen($packet);

        for ($i = 0; $i < $length; $i += 2) {
            $chksum += ord($packet[$i]) + ((ord($packet[$i + 1] ?? 0)) << 8);
            $chksum = $chksum & 0xFFFF;
        }

        return (~$chksum + 1) & 0xFFFF;
    }

    // 🔌 CONNECT
    public function connect()
    {
        $this->socket = stream_socket_client(
            "udp://{$this->ip}:{$this->port}",
            $errno,
            $errstr,
            5
        );

        if (! $this->socket) {
            throw new \Exception("Connection failed: $errstr ($errno)");
        }

        stream_set_timeout($this->socket, 3);

        // 🔥 include comm key
        $data = pack('V', $this->comm_key);

        $this->send(self::CMD_CONNECT, $data);

        $res = $this->receive();

        if (! $res || strlen($res) < 8) {
            throw new \Exception('No response (wrong comm key / UDP issue)');
        }

        $header = unpack('vcommand/vchecksum/vsession/vreply', substr($res, 0, 8));
        $this->session_id = $header['session'];

        return true;
    }

    // 🔌 DISCONNECT
    public function disconnect()
    {
        $this->send(self::CMD_EXIT);
        fclose($this->socket);
    }

    // 📤 SEND
    protected function send($command, $data = '')
    {
        $this->reply_id++;

        $packet = pack('vvvv', $command, 0, $this->session_id, $this->reply_id).$data;

        $checksum = $this->checksum($packet);

        $packet = pack('vvvv', $command, $checksum, $this->session_id, $this->reply_id).$data;

        fwrite($this->socket, $packet);
    }

    // 📥 RECEIVE (UDP safe)
    protected function receive()
    {
        $read = [$this->socket];
        $write = $except = [];

        $data = '';

        if (stream_select($read, $write, $except, 3)) {
            $data = fread($this->socket, 65535);
        }

        return $data;
    }

    // 📊 FETCH LOGS
    public function fetchAttendanceRaw()
    {
        $this->send(self::CMD_ATTLOG_RRQ);

        $response = $this->receive();

        if (! $response || strlen($response) < 8) {
            return null;
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
