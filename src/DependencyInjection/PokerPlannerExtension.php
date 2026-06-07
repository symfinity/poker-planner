<?php

declare(strict_types=1);

namespace Symfinity\Bundle\PokerPlanner\DependencyInjection;

use Symfinity\Bundle\PokerPlanner\Storage\RedisRoomStore;
use Symfinity\Bundle\PokerPlanner\Storage\RoomStoreInterface;
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
        if ($container->hasExtension('framework')) {
            $container->prependExtensionConfig('framework', [
                'asset_mapper' => [
                    'excluded_patterns' => [
                        'bundles/pokerplanner/**',
                    ],
                ],
                'assets' => [
                    'packages' => [
                        'poker_planner' => [
                            'base_path' => '/bundles/pokerplanner',
                        ],
                    ],
                ],
            ]);
        }

        $container->prependExtensionConfig('twig', [
            'paths' => [
                __DIR__ . '/../../templates' => 'SymfinityPokerPlanner',
            ],
        ]);
    }

    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $container->setParameter('poker_planner.path_prefix', $config['path_prefix'] ?? '');
        $container->setParameter('poker_planner.mercure_topic_prefix', $config['mercure_topic_prefix']);
        $container->setParameter('poker_planner.storage.redis_url', $config['storage']['redis_url']);
        $container->setParameter('poker_planner.storage.prefix', $config['storage']['prefix']);
        $container->setParameter('poker_planner.room.max_ttl_seconds', $config['room']['max_ttl_seconds']);
        $container->setParameter('poker_planner.room.grace_seconds', $config['room']['grace_seconds']);
        $container->setParameter('poker_planner.room.heartbeat_seconds', $config['room']['heartbeat_seconds']);

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
