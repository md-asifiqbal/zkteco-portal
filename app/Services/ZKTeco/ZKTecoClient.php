<?php

namespace App\Services\ZKTeco;

class ZKTecoClient
{
    public $ip;

    public $port;

    public $socket;

    public $session_id = 0;

    public $reply_id = 0;

    public $protocol = 'udp';

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
        // 1. TRY UDP FIRST (Standard for most older/mixed ZK devices)
        $this->protocol = 'udp';
        $this->socket = @fsockopen('udp://'.$this->ip, $this->port, $udpErrno, $udpErrstr, 5);

        if ($this->socket) {
            stream_set_timeout($this->socket, 5);
            $this->send(self::CMD_CONNECT);
            $res = $this->receive();

            if ($res && strlen($res) >= 8) {
                $header = unpack('vcommand/vchecksum/vsession/vreply', substr($res, 0, 8));
                $this->session_id = $header['session'];
                return true;
            }
            @fclose($this->socket);
        }

        // 2. FALLBACK TO TCP (Modern F22, IFace, Visible Light firmwares)
        $this->protocol = 'tcp';
        $this->socket = @fsockopen('tcp://'.$this->ip, $this->port, $tcpErrno, $tcpErrstr, 5);

        if (! $this->socket) {
            throw new \Exception("ZKTeco Connection failed entirely. UDP was attempted but returned zero bytes (device ignoring packets), and TCP fallback threw: $tcpErrstr");
        }

        stream_set_timeout($this->socket, 5);

        $this->send(self::CMD_CONNECT);
        $res = $this->receive();

        if (empty($res) || strlen($res) < 8) {
            throw new \Exception('Connected successfully via TCP, but device gave no response to commands. Check Comm Key.');
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
        $this->reply_id++;
        if ($this->reply_id >= 0xFFFF) {
            $this->reply_id = 0;
        }

        $buf = pack('vvvv', $command, 0, $this->session_id, $this->reply_id).$data;
        $checksum = $this->createChecksum($buf);
        $packet = pack('vvvv', $command, $checksum, $this->session_id, $this->reply_id).$data;

        if ($this->protocol === 'tcp') {
            // TCP Envelope includes prepended \x50\x50\x82\x7d + Size
            $sizePack = pack('V', strlen($packet));
            $envelope = "\x50\x50\x82\x7d" . $sizePack;
            $packet = $envelope . $packet;
        }

        fwrite($this->socket, $packet);
    }

    public function receive()
    {
        if ($this->protocol === 'tcp') {
            // TCP stream requires unpacking the envelope header
            $header = fread($this->socket, 8);
            if (! $header || strlen($header) < 8) {
                return false;
            }

            // Extract the body size
            $size = unpack('V', substr($header, 4, 4))[1];

            $data = '';
            while (strlen($data) < $size) {
                $chunk = fread($this->socket, $size - strlen($data));
                if ($chunk === false || strlen($chunk) == 0) {
                    break;
                }
                $data .= $chunk;
            }

            return $data;
        } else {
            return fread($this->socket, 65535);
        }
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
