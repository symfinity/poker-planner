<?php

declare(strict_types=1);

namespace Symfinity\Bundle\PokerPlanner\Storage;

/**
 * Minimal Redis port — ext-redis or Predis.
 */
interface RedisClientInterface
{
    public function get(string $key): ?string;

    public function setex(string $key, int $ttl, string $value): void;

    public function del(string $key): void;
}
