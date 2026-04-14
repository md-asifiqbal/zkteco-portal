<?php

namespace App\Services\ZKTeco\Architectures;

interface ArchitectureInterface
{
    /**
     * Get the expected byte size of a user record from the device flash memory.
     */
    public function getUserPacketSize(): int;

    /**
     * Parse a binary chunk into a User array.
     */
    public function parseUserChunk(string $chunk): ?array;

    /**
     * Build a binary packet to create/update a user.
     */
    public function buildUserPacket(int $uid, string $name, int $role = 0, string $userId = ''): string;
}
