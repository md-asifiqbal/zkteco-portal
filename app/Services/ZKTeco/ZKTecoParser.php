<?php

namespace App\Services\ZKTeco;

use Carbon\Carbon;

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

            $parsed = $this->parseChunk($chunk, $recordSize);

            if ($parsed) {
                $records[] = $parsed;
            }

            $offset += $recordSize;
        }

        return $records;
    }

    // 🔍 Detect record size dynamically
    protected function detectRecordSize($data)
    {
        foreach ([16, 18, 24] as $size) {
            if (strlen($data) % $size === 0) {
                return $size;
            }
        }

        return 16; // fallback
    }

    protected function parseChunk($chunk, $size)
    {
        $uid = unpack('v', substr($chunk, 0, 2))[1] ?? null;

        $userRaw = substr($chunk, 2, 9);
        $user_id = $this->cleanString($userRaw);

        // 🔥 Try both layouts
        $layouts = [
            ['verify' => 11, 'status' => 12, 'time' => 12],
            ['verify' => 11, 'status' => 12, 'time' => 13],
            ['verify' => 12, 'status' => 11, 'time' => 12],
        ];

        foreach ($layouts as $layout) {

            $verify_type = ord($chunk[$layout['verify']] ?? 0);
            $status = ord($chunk[$layout['status']] ?? 0);

            $timeRaw = unpack('V', substr($chunk, $layout['time'], 4))[1] ?? null;

            $timestamp = $this->decodeTime($timeRaw);

            if ($this->isValidTime($timestamp)) {
                return [
                    'uid' => $uid,
                    'user_id' => $user_id,
                    'timestamp' => $timestamp,
                    'status' => $status,
                    'verify_type' => $verify_type,
                ];
            }
        }

        return null; // skip invalid record
    }

    protected function decodeTime($time)
    {
        if (! $time) {
            return null;
        }

        return Carbon::create(2000, 1, 1)->addSeconds($time);
    }

    // 🔥 Smart string cleaner (multi-encoding)
    protected function cleanString($value)
    {
        if (! $value) {
            return null;
        }

        // Try UTF-8 first
        $value = @mb_convert_encoding($value, 'UTF-8', 'UTF-8');

        // Try GBK → UTF-8 (common in ZKTeco)
        $value = @mb_convert_encoding($value, 'UTF-8', 'GBK');

        // Remove null bytes
        $value = str_replace("\0", '', $value);

        // Remove garbage characters
        return trim(preg_replace('/[^\x20-\x7E]/', '', $value));
    }

    protected function isValidTime($time)
    {
        return $time &&
               $time->year >= 2000 &&
               $time->year <= now()->year + 1;
    }
}
