<?php

declare(strict_types=1);

namespace Symfinity\Bundle\PokerPlanner\Storage;

use Symfinity\Bundle\PokerPlanner\Model\Room;

final class RedisRoomStore implements RoomStoreInterface
{
    private RedisClientInterface $redis;

    public function __construct(
        string $redisUrl,
        private readonly string $prefix = 'poker_planner',
    ) {
        $this->redis = RedisClientFactory::create($redisUrl);
    }

    public function get(string $roomId): ?Room
    {
        $raw = $this->redis->get($this->key($roomId));
        if (null === $raw || '' === $raw) {
            return null;
        }

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

        return Room::fromArray($decoded);
    }

    public function save(Room $room, int $ttlSeconds): void
    {
        $payload = json_encode($room->toArray(), JSON_THROW_ON_ERROR);
        $this->redis->setex($this->key($room->id), $ttlSeconds, $payload);
    }

    public function delete(string $roomId): void
    {
        $this->redis->del($this->key($roomId));
    }

    private function key(string $roomId): string
    {
        return sprintf('%s:room:%s', $this->prefix, $roomId);
    }
}
