<?php

declare(strict_types=1);

namespace Symfinity\Bundle\PokerPlanner;

use Symfony\Component\HttpKernel\Bundle\Bundle;

final class PokerPlannerBundle extends Bundle
{
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
