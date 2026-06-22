<?php

declare(strict_types=1);

namespace Symfinity\Bundle\PokerPlanner;

use Symfinity\Bundle\PokerPlanner\DependencyInjection\PokerPlannerExtension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

final class PokerPlannerBundle extends Bundle
{
    /** Org policy: config root {@code symfinity_poker_planner} (rule 22). */
    public function getContainerExtension(): ?ExtensionInterface
    {
        return new PokerPlannerExtension();
    }

    public function getContainerExtensionClass(): string
    {
        return PokerPlannerExtension::class;
    }

    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
