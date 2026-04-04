<?php

namespace App\Services\ZKTeco;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class ZKTecoParser
{
    protected $recordSizes = [16, 20, 24, 40];

    protected $userIdLengths = [9, 12, 16];

    public function parse($data)
    {
        $best = [
            'size' => null,
            'score' => 0,
            'records' => [],
        ];

        foreach ($this->recordSizes as $size) {

            $records = $this->parseWithSize($data, $size);

            $score = $this->scoreRecords($records);

            if ($score > $best['score']) {
                $best = [
                    'size' => $size,
                    'score' => $score,
                    'records' => $records,
                ];
            }
        }

        Log::info('ZKTeco Parser Selected Format', [
            'record_size' => $best['size'],
            'score' => $best['score'],
            'total_records' => count($best['records']),
        ]);

        return $best['records'];
    }

    protected function parseWithSize($data, $size)
    {
        $records = [];
        $length = strlen($data);

        for ($offset = 0; $offset + $size <= $length; $offset += $size) {

            $chunk = substr($data, $offset, $size);

            $parsed = $this->parseChunkFlexible($chunk);

            if ($parsed) {
                $records[] = $parsed;
            }
        }

        return $records;
    }

    protected function parseChunkFlexible($chunk)
    {
        $len = strlen($chunk);

        if ($len < 16) {
            return null;
        }

        $uid = unpack('v', substr($chunk, 0, 2))[1] ?? null;

        foreach ($this->userIdLengths as $userLen) {

            if ($len < (2 + $userLen + 6)) {
                continue;
            }

            $user_id = $this->cleanString(substr($chunk, 2, $userLen));

            if (! $user_id) {
                continue;
            }

            $verifyIndex = 2 + $userLen;
            $statusIndex = $verifyIndex + 1;
            $timeIndex = $statusIndex + 1;

            if (! isset($chunk[$timeIndex + 3])) {
                continue;
            }

            $timeRaw = unpack('V', substr($chunk, $timeIndex, 4))[1] ?? null;

            $timestamp = $this->decodeTime($timeRaw);

            if (! $this->isValidTime($timestamp)) {
                continue;
            }

            return [
                'uid' => $uid,
                'user_id' => $user_id,
                'timestamp' => $timestamp,
                'status' => ord($chunk[$statusIndex] ?? 0),
                'verify_type' => ord($chunk[$verifyIndex] ?? 0),
                'raw_hex' => bin2hex($chunk), // debug ready
            ];
        }

        return null;
    }

    protected function scoreRecords($records)
    {
        $score = 0;

        foreach ($records as $r) {

            if (! $r['user_id']) {
                continue;
            }

            // ✔ valid user_id
            if (strlen($r['user_id']) >= 3) {
                $score += 2;
            }

            // ✔ realistic timestamp
            if ($this->isValidTime($r['timestamp'])) {
                $score += 3;
            }

            // ✔ valid status
            if ($r['status'] >= 0 && $r['status'] <= 5) {
                $score += 1;
            }

            // ✔ valid verify_type
            if (in_array($r['verify_type'], [0, 1, 2, 15])) {
                $score += 1;
            }
        }

        return $score;
    }

    protected function decodeTime($time)
    {
        if (! $time) {
            return null;
        }

        return Carbon::create(2000, 1, 1)->addSeconds($time);
    }

    protected function isValidTime($time)
    {
        return $time &&
            $time->year >= 2000 &&
            $time->year <= now()->year + 1;
    }

    protected function cleanString($value)
    {
        if (! $value) {
            return null;
        }

        $value = str_replace("\0", '', $value);

        return trim(preg_replace('/[^\x20-\x7E]/', '', $value));
    }
}
