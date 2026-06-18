<?php

declare(strict_types=1);

namespace Symfinity\Bundle\PokerPlanner\Model;

enum DeckPreset: string
{
    case Fibonacci = 'fibonacci';
    case ModifiedFibonacci = 'modified_fibonacci';
    case PowersOfTwo = 'powers_of_two';
    case ShirtSizes = 'shirt_sizes';
    case Fibonaccino = 'fibonaccino';

    public function label(): string
    {
        return match ($this) {
            self::Fibonacci => 'Fibonacci',
            self::ModifiedFibonacci => 'Modified fibonacci',
            self::PowersOfTwo => 'Powers of two',
            self::ShirtSizes => 'Shirt sizes',
            self::Fibonaccino => 'Fibonaccino',
        };
    }

    /**
     * @return list<CardValue>
     */
    public function baseCards(): array
    {
        return match ($this) {
            self::Fibonacci => [
                CardValue::One,
                CardValue::Two,
                CardValue::Three,
                CardValue::Five,
                CardValue::Eight,
                CardValue::Thirteen,
                CardValue::TwentyOne,
                CardValue::ThirtyFour,
                CardValue::FiftyFive,
                CardValue::EightyNine,
            ],
            self::ModifiedFibonacci => [
                CardValue::Half,
                CardValue::One,
                CardValue::Two,
                CardValue::Three,
                CardValue::Five,
                CardValue::Eight,
                CardValue::Thirteen,
                CardValue::Twenty,
                CardValue::Forty,
                CardValue::Hundred,
            ],
            self::PowersOfTwo => [
                CardValue::One,
                CardValue::Two,
                CardValue::Four,
                CardValue::Eight,
                CardValue::Sixteen,
            ],
            self::ShirtSizes => [
                CardValue::ExtraSmall,
                CardValue::Small,
                CardValue::Medium,
                CardValue::Large,
                CardValue::ExtraLarge,
            ],
            self::Fibonaccino => [
                CardValue::One,
                CardValue::Two,
                CardValue::Three,
                CardValue::Five,
                CardValue::Eight,
            ],
        };
    }
}
