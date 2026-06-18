<?php

declare(strict_types=1);

namespace Symfinity\Bundle\PokerPlanner\DependencyInjection;

use Symfinity\Bundle\PokerPlanner\Storage\RedisRoomStore;
use Symfinity\Bundle\PokerPlanner\Storage\RoomStoreInterface;
use Symfinity\Bundle\PokerPlanner\Support\Coerce;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

final class PokerPlannerExtension extends Extension implements PrependExtensionInterface
{
    public function prepend(ContainerBuilder $container): void
    {
        $packageDir = \dirname(__DIR__, 2);

        if ($container->hasExtension('framework')) {
            $container->prependExtensionConfig('framework', [
                'asset_mapper' => [
                    'paths' => [
                        $packageDir . '/assets' => 'poker-planner',
                    ],
                ],
            ]);
        }

        $container->prependExtensionConfig('twig', [
            'paths' => [
                $packageDir . '/templates' => 'SymfinityPokerPlanner',
            ],
        ]);
    }

    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        /** @var array<string, mixed> $config */
        $config = $this->processConfiguration($configuration, $configs);

        $storage = Coerce::arrayMap($config['storage'] ?? null);
        $room = Coerce::arrayMap($config['room'] ?? null);

        $container->setParameter('poker_planner.path_prefix', Coerce::string($config['path_prefix'] ?? null));
        $container->setParameter('poker_planner.mercure_topic_prefix', Coerce::string($config['mercure_topic_prefix'] ?? null));
        $container->setParameter('poker_planner.storage.redis_url', Coerce::string($storage['redis_url'] ?? null, '%env(REDIS_URL)%'));
        $container->setParameter('poker_planner.storage.prefix', Coerce::string($storage['prefix'] ?? null, 'poker_planner'));
        $container->setParameter('poker_planner.room.max_ttl_seconds', Coerce::int($room['max_ttl_seconds'] ?? null, 14_400));
        $container->setParameter('poker_planner.room.saved_ttl_seconds', Coerce::int($room['saved_ttl_seconds'] ?? null, 31_536_000));
        $container->setParameter('poker_planner.room.grace_seconds', Coerce::int($room['grace_seconds'] ?? null, 600));
        $container->setParameter('poker_planner.room.heartbeat_seconds', Coerce::int($room['heartbeat_seconds'] ?? null, 30));

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../../config'));
        $loader->load('services.yaml');

        $container->setDefinition(RoomStoreInterface::class, (new Definition(RedisRoomStore::class))
            ->setAutowired(false)
            ->setLazy(true)
            ->setArguments([
                '%poker_planner.storage.redis_url%',
                '%poker_planner.storage.prefix%',
            ]));
    }

    public function getAlias(): string
    {
        return 'poker_planner';
    }
}
