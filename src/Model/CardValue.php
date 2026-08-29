<?php

declare(strict_types=1);

namespace Symfinity\Bundle\PokerPlanner\Model;

enum CardValue: string
{
    case Half = 'half';
    case Zero = '0';
    case One = '1';
    case Two = '2';
    case Three = '3';
    case Four = '4';
    case Five = '5';
    case Eight = '8';
    case Thirteen = '13';
    case Sixteen = '16';
    case Twenty = '20';
    case TwentyOne = '21';
    case ThirtyFour = '34';
    case Forty = '40';
    case FiftyFive = '55';
    case EightyNine = '89';
    case Hundred = '100';
    case ExtraSmall = 'xs';
    case Small = 's';
    case Medium = 'm';
    case Large = 'l';
    case ExtraLarge = 'xl';
    case Pass = 'pass';
    case Break = 'break';
    case Unknown = '?';
    case Coffee = 'coffee';

    public function label(): string
    {
        return match ($this) {
            self::Half => '½',
            self::Zero => '0',
            self::Pass => 'Pass',
            self::Break, self::Coffee => 'Break',
            self::ExtraSmall => 'XS',
            self::Small => 'S',
            self::Medium => 'M',
            self::Large => 'L',
            self::ExtraLarge => 'XL',
            default => $this->value,
        };
    }

    public function isNumeric(): bool
    {
        return null !== $this->numericValue();
    }

    public function numericValue(): ?float
    {
        return match ($this) {
            self::Half => 0.5,
            self::Zero => 0.0,
            self::One => 1.0,
            self::Two => 2.0,
            self::Three => 3.0,
            self::Four => 4.0,
            self::Five => 5.0,
            self::Eight => 8.0,
            self::Thirteen => 13.0,
            self::Sixteen => 16.0,
            self::Twenty => 20.0,
            self::TwentyOne => 21.0,
            self::ThirtyFour => 34.0,
            self::Forty => 40.0,
            self::FiftyFive => 55.0,
            self::EightyNine => 89.0,
            self::Hundred => 100.0,
            self::Unknown, self::Coffee, self::Break, self::Pass,
            self::ExtraSmall, self::Small, self::Medium, self::Large, self::ExtraLarge => null,
        };
    }

    public function accent(): string
    {
        $numeric = $this->numericValue();
        if (null === $numeric) {
            return 'grey';
        }

        if ($numeric <= 1.0) {
            return 'sky';
        }

        if ($numeric <= 3.0) {
            return 'mint';
        }

        if ($numeric <= 8.0) {
            return 'amber';
        }

        return 'coral';
    }

    public function icon(): ?string
    {
        return match ($this) {
            self::Pass => 'pass',
            self::Break, self::Coffee => 'coffee',
            default => null,
        };
    }
}
