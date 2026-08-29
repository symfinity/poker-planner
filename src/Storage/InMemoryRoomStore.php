<?php

declare(strict_types=1);

namespace Symfinity\Bundle\PokerPlanner\Storage;

use Symfinity\Bundle\PokerPlanner\Model\Room;

final class InMemoryRoomStore implements RoomStoreInterface
{
    /** @var array<string, Room> */
    private array $rooms = [];

    public function get(string $roomId): ?Room
    {
        return $this->rooms[$roomId] ?? null;
    }

    public function save(Room $room, int $ttlSeconds): void
    {
        $this->rooms[$room->id] = Room::fromArray($room->toArray());
    }

    public function delete(string $roomId): void
    {
        unset($this->rooms[$roomId]);
    }
}
