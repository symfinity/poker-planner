<?php

declare(strict_types=1);

namespace Symfinity\Bundle\PokerPlanner\Model;

enum Phase: string
{
    case Voting = 'voting';
    case Revealed = 'revealed';
}
