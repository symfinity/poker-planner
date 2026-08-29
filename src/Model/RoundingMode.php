<?php

declare(strict_types=1);

namespace Symfinity\Bundle\PokerPlanner\Model;

enum RoundingMode: string
{
    case Nearest = 'nearest';
    case Up = 'up';
    case Down = 'down';

    public function label(): string
    {
        return match ($this) {
            self::Nearest => 'Nearest',
            self::Up => 'Round up',
            self::Down => 'Round down',
        };
    }

    public function apply(float $value): float
    {
        return match ($this) {
            self::Nearest => round($value),
            self::Up => ceil($value),
            self::Down => floor($value),
        };
    }
}
