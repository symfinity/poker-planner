<?php

declare(strict_types=1);

namespace Symfinity\Bundle\PokerPlanner\Model;

enum CardValue: string
{
    case Half = 'half';
    case One = '1';
    case Two = '2';
    case Three = '3';
    case Five = '5';
    case Eight = '8';
    case Thirteen = '13';
    case TwentyOne = '21';
    case Unknown = '?';
    case Coffee = 'coffee';

    public function label(): string
    {
        return match ($this) {
            self::Half => '½',
            self::Coffee => '☕',
            default => $this->value,
        };
    }

    public function isNumeric(): bool
    {
        return match ($this) {
            self::Unknown, self::Coffee => false,
            default => true,
        };
    }

    public function accent(): string
    {
        return match ($this) {
            self::Half, self::One => 'sky',
            self::Two, self::Three => 'mint',
            self::Five, self::Eight => 'amber',
            self::Thirteen, self::TwentyOne => 'coral',
            self::Unknown, self::Coffee => 'grey',
        };
    }

    /**
     * @return list<self>
     */
    public static function deck(): array
    {
        return [
            self::Half,
            self::One,
            self::Two,
            self::Three,
            self::Five,
            self::Eight,
            self::Thirteen,
            self::TwentyOne,
            self::Unknown,
            self::Coffee,
        ];
    }
}
