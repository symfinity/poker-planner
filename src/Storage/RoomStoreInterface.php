<?php

declare(strict_types=1);

namespace Symfinity\Bundle\PokerPlanner\Storage;

use Symfinity\Bundle\PokerPlanner\Model\Room;

interface RoomStoreInterface
{
    public function get(string $roomId): ?Room;

    public function save(Room $room, int $ttlSeconds): void;

    public function delete(string $roomId): void;
}
