<?php

namespace App\Services\ZKTeco;

use Carbon\Carbon;

class ZKTecoParser
{
    public function parseAttendance($data)
    {
        $records = [];
        $offset = 0;
        $length = strlen($data);

        while ($offset + 16 <= $length) {
            $chunk = substr($data, $offset, 16);

            $uid = unpack('v', substr($chunk, 0, 2))[1];
            $user_id = trim(str_replace("\0", '', substr($chunk, 2, 9)));
            $status = ord($chunk[11]);
            $verify_type = ord($chunk[12]);
            $timestamp = unpack('V', substr($chunk, 12, 4))[1];

            $records[] = [
                'uid' => $uid,
                'user_id' => $user_id,
                'timestamp' => $this->decodeTime($timestamp),
                'status' => $status,
                'verify_type' => $verify_type,
            ];

            $offset += 16;
        }

        return $records;
    }

    protected function decodeTime($time)
    {
        return Carbon::create(2000, 1, 1)->addSeconds($time);
    }
}
