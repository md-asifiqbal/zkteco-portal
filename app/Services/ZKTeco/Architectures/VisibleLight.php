<?php

namespace App\Services\ZKTeco\Architectures;

class VisibleLight implements ArchitectureInterface
{
    public function getUserPacketSize(): int
    {
        return 72; // VL devices typically use 72-byte structs for Users
    }

    public function parseUserChunk(string $chunk): ?array
    {
        if (strlen($chunk) < 72) {
            return null;
        }

        return [
            'uid' => unpack('v', substr($chunk, 0, 2))[1] ?? null,
            'user_id' => $this->extractUserId($chunk),
            'name' => $this->cleanString(substr($chunk, 11, 24)),
            'role' => ord($chunk[35] ?? 0),
            'raw_hex' => bin2hex($chunk),
        ];
    }

    public function buildUserPacket(int $uid, string $name, int $role = 0, string $userId = ''): string
    {
        $packet = str_repeat("\x00", 72);

        // 1. UID (2 bytes, Offset 0)
        $uidBin = pack('v', $uid);
        $packet[0] = $uidBin[0];
        $packet[1] = $uidBin[1];

        // 2. Role (Offset 2 and 35)
        $packet[2] = chr($role);
        $packet[35] = chr($role);

        // 3. Name (Offset 11, length 24)
        $nameBin = str_pad(substr($name, 0, 24), 24, "\x00");
        for ($i = 0; $i < 24; $i++) {
            $packet[11 + $i] = $nameBin[$i];
        }

        // 4. User ID (Offset 48 length 24 for 72-byte)
        $userId = $userId ?: (string) $uid;
        $userIdBin = str_pad(substr($userId, 0, 24), 24, "\x00");
        for ($i = 0; $i < 24; $i++) {
            $packet[48 + $i] = $userIdBin[$i];
        }

        return $packet;
    }

    protected function extractUserId(string $chunk): ?string
    {
        $hex = bin2hex($chunk);

        // Look for internal prefix "SS-EMP-" or just use offset 48 string directly:
        if (preg_match('/53532d454d502d[0-9]+/', $hex, $match)) {
            return hex2bin($match[0]);
        }

        // Normal visible light devices store string user id at offset 48
        $id = $this->cleanString(substr($chunk, 48, 24));
        if (!empty($id)) {
            return $id;
        }

        return null;
    }

    protected function cleanString(?string $value): ?string
    {
        if (!$value) {
            return null;
        }

        $value = str_replace("\0", '', $value);
        $value = preg_replace('/[^\x20-\x7E]/', '', $value);
        $value = iconv('UTF-8', 'UTF-8//IGNORE', $value);

        return trim($value);
    }
}
