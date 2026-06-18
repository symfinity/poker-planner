<?php

declare(strict_types=1);

namespace Symfinity\Bundle\PokerPlanner\Storage;

final class ExtRedisClient implements RedisClientInterface
{
    private \Redis $redis;

    public function __construct(string $redisUrl)
    {
        $this->redis = new \Redis();

        $parts = parse_url($redisUrl);
        if (!is_array($parts)) {
            throw new \InvalidArgumentException('Invalid REDIS_URL for poker-planner.');
        }

        $host = $parts['host'] ?? '127.0.0.1';
        $port = (int) ($parts['port'] ?? 6379);
        $this->redis->connect($host, $port);

        $password = $parts['pass'] ?? null;
        if (is_string($password) && '' !== $password) {
            $this->redis->auth($password);
        }

        $path = $parts['path'] ?? null;
        if (is_string($path) && '' !== $path && '/' !== $path) {
            $this->redis->select((int) ltrim($path, '/'));
        }
    }

    public function get(string $key): ?string
    {
        $value = $this->redis->get($key);
        if (false === $value || !is_string($value)) {
            return null;
        }

        return $value;
    }

    public function setex(string $key, int $ttl, string $value): void
    {
        $this->redis->setex($key, $ttl, $value);
    }

    public function del(string $key): void
    {
        $this->redis->del($key);
    }
}
