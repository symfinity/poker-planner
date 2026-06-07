<?php

declare(strict_types=1);

namespace Symfinity\Bundle\PokerPlanner\Storage;

final class RedisClientFactory
{
    public static function create(string $redisUrl): RedisClientInterface
    {
        if (extension_loaded('redis') && class_exists(\Redis::class)) {
            return new ExtRedisClient($redisUrl);
        }

        if (class_exists(\Predis\Client::class)) {
            return new PredisRedisClient($redisUrl);
        }

        throw new \RuntimeException('poker-planner requires ext-redis or predis/predis for room storage.');
    }
}
