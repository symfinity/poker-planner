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

        if (isset($parts['pass']) && is_string($parts['pass']) && '' !== $parts['pass']) {
            $this->redis->auth($parts['pass']);
        }

        if (isset($parts['path']) && is_string($parts['path']) && '' !== $parts['path'] && '/' !== $parts['path']) {
            $this->redis->select((int) ltrim($parts['path'], '/'));
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
