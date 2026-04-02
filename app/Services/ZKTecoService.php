<?php

namespace App\Services\ZKTeco;

class ZKTecoService
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

    // 🔌 Connect to device
    public function connect()
    {
        $this->socket = fsockopen($this->ip, $this->port, $errno, $errstr, 5);

        if (! $this->socket) {
            throw new \Exception("Connection failed: $errstr ($errno)");
        }

        stream_set_timeout($this->socket, 5);

        $this->sendCommand(self::CMD_CONNECT);

        $response = $this->receive();

        $header = unpack('vcommand/vchecksum/vsession/vreply', substr($response, 0, 8));

        $this->session_id = $header['session'];
        $this->reply_id = $header['reply'];

        return true;
    }

    // 📤 Send command
    protected function sendCommand($command, $data = '')
    {
        $this->reply_id++;

        $packet = pack(
            'vvvv',
            $command,
            0,
            $this->session_id,
            $this->reply_id
        ).$data;

        fwrite($this->socket, $packet);
    }

    // 📥 Receive data
    protected function receive($length = 8192)
    {
        return fread($this->socket, $length);
    }

    // 📦 Read multi-packet response
    protected function readData()
    {
        $fullData = '';

        while (true) {
            $response = $this->receive();

            if (! $response) {
                break;
            }

            $header = unpack('vcommand/vchecksum/vsession/vreply', substr($response, 0, 8));
            $command = $header['command'];

            $body = substr($response, 8);

            if ($command == self::CMD_DATA) {
                $fullData .= $body;
            }

            if ($command == self::CMD_FREE_DATA) {
                break;
            }
        }

        return $fullData;
    }

    // 📊 Get attendance logs
    public function getAttendance()
    {
        $this->sendCommand(self::CMD_ATTLOG_RRQ);

        // First response (prepare)
        $this->receive();

        $data = $this->readData();

        return $this->parseAttendance($data);
    }

    // 🔍 Parse attendance binary
    protected function parseAttendance($data)
    {
        $records = [];

        $length = strlen($data);
        $offset = 0;

        while ($offset + 16 <= $length) {
            $chunk = substr($data, $offset, 16);

            $unpacked = unpack('vuid/Vtimestamp/Cstatus', $chunk);

            $records[] = [
                'user_id' => $unpacked['uid'],
                'timestamp' => $this->decodeTime($unpacked['timestamp']),
                'status' => $unpacked['status'],
            ];

            $offset += 16;
        }

        return $records;
    }

    // ⏱ Decode ZKTeco timestamp
    protected function decodeTime($time)
    {
        $second = $time % 60;
        $time = intdiv($time, 60);

        $minute = $time % 60;
        $time = intdiv($time, 60);

        $hour = $time % 24;
        $time = intdiv($time, 24);

        $day = $time;

        return now()->startOfYear()
            ->addDays($day)
            ->addHours($hour)
            ->addMinutes($minute)
            ->addSeconds($second);
    }

    // 🔌 Disconnect
    public function disconnect()
    {
        $this->sendCommand(self::CMD_EXIT);

        fclose($this->socket);
    }
}
