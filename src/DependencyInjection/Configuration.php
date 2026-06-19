<?php

declare(strict_types=1);

namespace Symfinity\Bundle\PokerPlanner\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('symfinity_poker_planner');

        $root = $treeBuilder->getRootNode();

        $root
            ->children()
                ->scalarNode('path_prefix')->defaultValue('')->end()
                ->scalarNode('mercure_topic_prefix')->defaultValue('')->end()
                ->arrayNode('storage')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('redis_url')->defaultValue('%env(REDIS_URL)%')->end()
                        ->scalarNode('prefix')->defaultValue('poker_planner')->end()
                    ->end()
                ->end()
                ->arrayNode('room')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->integerNode('max_ttl_seconds')->defaultValue(14_400)->end()
                        ->integerNode('saved_ttl_seconds')->defaultValue(31_536_000)->end()
                        ->integerNode('grace_seconds')->defaultValue(600)->end()
                        ->integerNode('heartbeat_seconds')->defaultValue(30)->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
