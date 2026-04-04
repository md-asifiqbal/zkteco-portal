<?php

namespace App\Services\ZKTeco;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class ZKTecoParser
{
    public function parseAttendance($data)
    {
        $recordSize = $this->detectRecordSize($data);

        $records = [];
        $offset = 0;
        $length = strlen($data);

        while ($offset + $recordSize <= $length) {

            $chunk = substr($data, $offset, $recordSize);

            if (strlen($chunk) < $recordSize) {
                break;
            }

            try {
                $parsed = $this->parseChunk($chunk);

                if ($parsed) {
                    $records[] = $parsed;
                }

            } catch (\Throwable $e) {
                Log::warning('Parser error', [
                    'error' => $e->getMessage(),
                    'hex' => bin2hex($chunk),
                ]);
            }

            $offset += $recordSize;
        }

        return $records;
    }

    protected function detectRecordSize($data)
    {
        $length = strlen($data);

        foreach ([16, 18, 24, 40] as $size) {

            $valid = 0;
            $checks = 0;

            for ($i = 0; $i + $size <= $length && $checks < 5; $i += $size) {

                $chunk = substr($data, $i, $size);

                if ($this->looksValidChunk($chunk)) {
                    $valid++;
                }

                $checks++;
            }

            if ($valid >= 3) {
                return $size;
            }
        }

        return 16;
    }

    protected function looksValidChunk($chunk)
    {
        if (strlen($chunk) < 16) {
            return false;
        }

        $timeBytes = substr($chunk, 12, 4);

        if (strlen($timeBytes) < 4) {
            return false;
        }

        $time = unpack('V', $timeBytes)[1] ?? null;

        return $this->isValidTime($this->decodeTime($time));
    }

    protected function parseChunk($chunk)
    {
        if (strlen($chunk) < 16) {
            return null;
        }

        $uid = unpack('v', substr($chunk, 0, 2))[1] ?? null;

        $user_id = $this->cleanString(substr($chunk, 2, 9));

        $layouts = [
            ['verify' => 11, 'status' => 12, 'time' => 12],
            ['verify' => 11, 'status' => 12, 'time' => 13],
            ['verify' => 12, 'status' => 11, 'time' => 12],
        ];

        foreach ($layouts as $layout) {

            if (! isset($chunk[$layout['time'] + 3])) {
                continue;
            }

            $timeRaw = unpack('V', substr($chunk, $layout['time'], 4))[1] ?? null;

            $timestamp = $this->decodeTime($timeRaw);

            if ($this->isValidTime($timestamp)) {

                return [
                    'uid' => $uid,
                    'user_id' => $user_id,
                    'timestamp' => $timestamp,
                    'status' => ord($chunk[$layout['status']] ?? 0),
                    'verify_type' => ord($chunk[$layout['verify']] ?? 0),
                    'stamp' => $this->extractStamp($chunk),
                ];
            }
        }

        return null;
    }

    protected function extractStamp($chunk)
    {
        if (strlen($chunk) >= 20) {
            $bytes = substr($chunk, -4);

            if (strlen($bytes) === 4) {
                return unpack('V', $bytes)[1] ?? null;
            }
        }

        return null;
    }

    protected function decodeTime($time)
    {
        if (! $time) {
            return null;
        }

        $t = Carbon::create(2000, 1, 1)->addSeconds($time);

        if ($this->isValidTime($t)) {
            return $t;
        }

        return null;
    }

    protected function cleanString($value)
    {
        if (! $value) {
            return null;
        }

        $value = str_replace("\0", '', $value);
        $value = iconv('UTF-8', 'UTF-8//IGNORE', $value);

        return trim(preg_replace('/[^\x20-\x7E]/', '', $value));
    }

    protected function isValidTime($time)
    {
        return $time &&
            $time->year >= 2000 &&
            $time->year <= now()->year + 1;
    }
}
