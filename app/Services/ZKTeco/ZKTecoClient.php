<?php

namespace App\Services\ZKTeco;

class ZKTecoClient
{
    protected $ip;
    protected $port;
    protected $socket;
    protected $session_id = 0;
    protected $reply_id = 0;
    protected $is_tcp = true; // Most newer devices on 4370 use TCP

    const CMD_CONNECT = 1000;
    const CMD_EXIT = 1001;
    const CMD_ATTLOG_RRQ = 13;
    const CMD_AUTH = 1102;
    const CMD_PREPARE_DATA = 1500;
    const CMD_DATA = 1501;
    const CMD_FREE_DATA = 1502;
    const CMD_ACK = 2000;

    public function __construct($ip, $port = 4370)
    {
        $this->ip = $ip;
        $this->port = $port;
    }

    /**
     * Logic for hashing the Comm Key (Password)
     */
    protected function makeCommKey($key, $session_id)
    {
        $res = 0;
        $key = (int)$key;
        for ($i = 0; $i < 32; $i++) {
            if ($key & (1 << $i)) {
                $res |= (1 << (31 - $i));
            }
        }
        return $res ^ $session_id;
    }

    protected function createChecksum($payload)
    {
        $acc = 0;
        if (strlen($payload) % 2 != 0) {
            $payload .= "\x00";
        }
        $words = unpack('v*', $payload);
        foreach ($words as $word) {
            $acc += $word;
            if ($acc > 0xffff) {
                $acc -= 0xffff;
            }
        }
        return ~$acc & 0xffff;
    }

    /**
     * Connect with optional 6-digit Comm Key
     */
    public function connect($commKey = 0)
    {
        // Try TCP by default for port 4370. Switch to "udp://" if your device is legacy.
        $protocol = $this->is_tcp ? "tcp://" : "udp://";
        $this->socket = fsockopen($protocol . $this->ip, $this->port, $errno, $errstr, 5);

        if (!$this->socket) {
            throw new \Exception("ZKTeco Connection failed: $errstr");
        }

        stream_set_timeout($this->socket, 5);

        // 1. Initial Connection Request
        $this->send(self::CMD_CONNECT);
        $res = $this->receive();

        if (empty($res)) {
            throw new \Exception("Device refused connection. Disable ADMS on device and ensure Comm Key is correct.");
        }

        $header = unpack('vcommand/vchecksum/vsession/vreply', $this->is_tcp ? substr($res, 4, 8) : substr($res, 0, 8));
        $this->session_id = $header['session'];

        // 2. Handle Authentication if Key is set (even if it's 000000)
        $hashedKey = $this->makeCommKey($commKey, $this->session_id);
        $this->send(self::CMD_AUTH, pack('V', $hashedKey));
        $authRes = $this->receive();

        return true;
    }

    public function disconnect()
    {
        $this->send(self::CMD_EXIT);
        if ($this->socket) {
            fclose($this->socket);
        }
    }

    protected function send($command, $data = '')
    {
        $this->reply_id++;
        if ($this->reply_id >= 0xffff) $this->reply_id = 0;

        $buf = pack('vvvv', $command, 0, $this->session_id, $this->reply_id) . $data;
        $checksum = $this->createChecksum($buf);
        $payload = pack('vvvv', $command, $checksum, $this->session_id, $this->reply_id) . $data;

        if ($this->is_tcp) {
            // TCP requires a 4-byte length prefix
            $packet = pack('V', strlen($payload)) . $payload;
        } else {
            $packet = $payload;
        }

        fwrite($this->socket, $packet);
    }

    protected function receive()
    {
        if ($this->is_tcp) {
            $header = fread($this->socket, 4);
            if (!$header) return null;
            $unpack = unpack('Vlen', $header);
            $info = fread($this->socket, $unpack['len']);
            return $header . $info;
        }

        return fread($this->socket, 65535);
    }

    public function fetchAttendanceRaw()
    {
        $this->send(self::CMD_ATTLOG_RRQ);
        $response = $this->receive();

        if (empty($response)) return null;

        $offset = $this->is_tcp ? 4 : 0;
        $header = unpack('vcommand', substr($response, $offset, 2));

        if ($header['command'] == self::CMD_PREPARE_DATA) {
            return $this->readMultiPacket();
        }

        return substr($response, $offset + 8);
    }

    protected function readMultiPacket()
    {
        $data = '';
        $offset = $this->is_tcp ? 4 : 0;

        while (true) {
            $res = $this->receive();
            if (!$res) break;

            $header = unpack('vcommand', substr($res, $offset, 2));
            $body = substr($res, $offset + 8);

            if ($header['command'] == self::CMD_DATA) {
                $data .= $body;
            }

            if ($header['command'] == self::CMD_FREE_DATA || $header['command'] == self::CMD_ACK) {
                break;
            }
        }

        return $data;
    }
}
