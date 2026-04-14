<?php

namespace App\Services\ZKTeco;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class ZKTecoParser
{
    protected $recordSizes = [20, 16, 24, 40]; // 🔥 prioritize 20 (your device)

    protected $userOffsets = [6, 2]; // 🔥 your device uses 6

    protected $userLengths = [12, 9, 16];

    public function parse($data, $architecture = null)
    {
        $best = [
            'size' => null,
            'score' => -1,
            'records' => [],
        ];

        $sizes = $this->recordSizes;
        if ($architecture) {
            if (basename(str_replace('\\', '/', get_class($architecture))) === 'VisibleLight') {
                $sizes = [40, 52]; // Strictly slice visible light at 40-byte / 52-byte structures
            } else {
                $sizes = [16, 20, 24, 32, 36]; // Standard sizes
            }
        }

        foreach ($sizes as $size) {

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

        // 🟢 40-Byte Structural Parsing (Visible Light / IFace Firmwares)
        if ($len == 40) {
            $timeRaw = unpack('V', substr($chunk, 34, 4))[1] ?? null;
            $timestamp = $this->decodeTime($timeRaw);

            return [
                'uid' => unpack('V', substr($chunk, 0, 4))[1] ?? 0,
                // MB10-VL explicitly stores the Employee ID string starting at Offset 8
                'user_id' => $this->cleanString(substr($chunk, 8, 24)), 
                'timestamp' => $this->isValidTime($timestamp) ? $timestamp : null,
                'status' => ord($chunk[33] ?? 0),
                'verify_type' => ord($chunk[32] ?? 0),
                'stamp' => $this->extractStamp($chunk),
                'raw_hex' => bin2hex($chunk),
            ];
        }

        // 🟡 Legacy Standard Parsing (16, 20, 24 bytes)
        $uid = unpack('v', substr($chunk, 0, 2))[1] ?? null;

        // 🔥 Try multiple offsets + lengths
        foreach ($this->userOffsets as $offset) {
            foreach ($this->userLengths as $length) {

                if ($len < ($offset + $length + 6)) {
                    continue;
                }

                $user_id = $this->cleanString(substr($chunk, $offset, $length));

                if (! $user_id) {
                    continue;
                }

                // 🔍 Try timestamp positions
                foreach ([12, 14, 16] as $timeIndex) {

                    if (! isset($chunk[$timeIndex + 3])) {
                        continue;
                    }

                    $timeRaw = unpack('V', substr($chunk, $timeIndex, 4))[1] ?? null;

                    $timestamp = $this->decodeTime($timeRaw);

                    if (! $this->isValidTime($timestamp)) {
                        continue;
                    }

                    $verify = ord($chunk[$timeIndex - 2] ?? 0);
                    $status = ord($chunk[$timeIndex - 1] ?? 0);

                    return [
                        'uid' => $uid,
                        'user_id' => $user_id,
                        'timestamp' => $timestamp,
                        'status' => $status,
                        'verify_type' => $verify,
                        'stamp' => $this->extractStamp($chunk), // ✅ added
                        'raw_hex' => bin2hex($chunk),
                    ];
                }
            }
        }

        return null;
    }

    /**
     * ✅ Extract stamp (last 4 bytes usually)
     */
    protected function extractStamp($chunk)
    {
        $len = strlen($chunk);

        if ($len < 4) {
            return null;
        }

        // try last 4 bytes
        $stampRaw = unpack('V', substr($chunk, -4))[1] ?? null;

        // filter unrealistic values
        if ($stampRaw && $stampRaw > 0 && $stampRaw < 4294967295) {
            return $stampRaw;
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

            if (strlen($r['user_id']) >= 3) {
                $score += 2;
            }

            if ($this->isValidTime($r['timestamp'])) {
                $score += 3;
            }

            if ($r['status'] >= 0 && $r['status'] <= 5) {
                $score += 1;
            }

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
