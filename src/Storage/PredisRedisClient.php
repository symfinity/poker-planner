<?php

declare(strict_types=1);

namespace Symfinity\Bundle\PokerPlanner\Storage;

final class PredisRedisClient implements RedisClientInterface
{
    /** @var \Predis\Client */
    private object $client;

    public function __construct(string $redisUrl)
    {
        $this->client = new \Predis\Client($redisUrl);
    }

    public function get(string $key): ?string
    {
        $value = $this->client->get($key);

        return is_string($value) ? $value : null;
    }

    public function setex(string $key, int $ttl, string $value): void
    {
        $this->client->setex($key, $ttl, $value);
    }

    public function del(string $key): void
    {
        $this->client->del([$key]);
    }
}
