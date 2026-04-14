<?php

namespace App\Services\ZKTeco\Architectures;

class Standard implements ArchitectureInterface
{
    public function getUserPacketSize(): int
    {
        return 28; // Standard TFT architecture uses 28 bytes
    }

    public function parseUserChunk(string $chunk): ?array
    {
        if (strlen($chunk) < 28) {
            return null;
        }

        // 28-byte TFT format generally looks like:
        // offset 0-1: UID
        // offset 2: Role
        // offset 3-10: Password 
        // offset 11-18: Name (8 chars)
        // offset 24-x: PIN / Card NO Depending on exact firm firmware
        
        $uid = unpack('v', substr($chunk, 0, 2))[1] ?? null;

        return [
            'uid' => $uid,
            'user_id' => (string) $uid, // TFT often just uses the integer UID as the user_id if string PIN isn't used
            'name' => $this->cleanString(substr($chunk, 11, 8)),
            'role' => ord($chunk[2] ?? 0),
            'raw_hex' => bin2hex($chunk),
        ];
    }

    public function buildUserPacket(int $uid, string $name, int $role = 0, string $userId = ''): string
    {
        $packet = str_repeat("\x00", 28);

        // 1. UID (2 bytes, Offset 0)
        $uidBin = pack('v', $uid);
        $packet[0] = $uidBin[0];
        $packet[1] = $uidBin[1];

        // 2. Role (1 byte, Offset 2)
        $packet[2] = chr($role);

        // 3. Name (Offset 11, length 8)
        $nameBin = str_pad(substr($name, 0, 8), 8, "\x00");
        for ($i = 0; $i < 8; $i++) {
            $packet[11 + $i] = $nameBin[$i];
        }

        return $packet;
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
