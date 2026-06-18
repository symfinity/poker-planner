<?php

declare(strict_types=1);

namespace Symfinity\Bundle\PokerPlanner\Service;

use Symfinity\Bundle\PokerPlanner\Model\CardValue;
use Symfinity\Bundle\PokerPlanner\Model\Room;
use Symfinity\Bundle\PokerPlanner\Model\RoomSettings;
use Symfinity\Bundle\PokerPlanner\Model\RoundingMode;

final class DeckBuilder
{
    /**
     * @return list<CardValue>
     */
    public function buildDeck(RoomSettings $settings): array
    {
        $deck = $settings->deckPreset->baseCards();

        if ($settings->optionalZero) {
            $deck[] = CardValue::Zero;
        }

        if ($settings->optionalPass) {
            $deck[] = CardValue::Pass;
        }

        if ($settings->optionalBreak) {
            $deck[] = CardValue::Break;
        }

        return $deck;
    }

    /**
     * @return list<CardValue>
     */
    public function buildForRoom(Room $room): array
    {
        return $this->buildDeck($room->settings);
    }

    /**
     * @return list<CardValue>
     */
    public function numericCardsForRoom(Room $room): array
    {
        $numeric = [];
        foreach ($this->buildForRoom($room) as $card) {
            if ($card->isNumeric()) {
                $numeric[] = $card;
            }
        }

        return $numeric;
    }

    public function deckIndexForNumeric(Room $room, float $value): ?int
    {
        foreach ($this->numericCardsForRoom($room) as $index => $card) {
            $numeric = $card->numericValue();
            if (null !== $numeric && abs($numeric - $value) < 0.001) {
                return $index;
            }
        }

        return null;
    }

    public function cardFromNumeric(Room $room, float $value): ?CardValue
    {
        foreach ($this->numericCardsForRoom($room) as $card) {
            $numeric = $card->numericValue();
            if (null !== $numeric && abs($numeric - $value) < 0.001) {
                return $card;
            }
        }

        return null;
    }

    public function snapMedianToDeck(Room $room, float $median, RoundingMode $mode): ?CardValue
    {
        $cards = $this->numericCardsForRoom($room);
        if ($cards === []) {
            return null;
        }

        return match ($mode) {
            RoundingMode::Nearest => $this->nearestCard($cards, $median),
            RoundingMode::Up => $this->ceilCard($cards, $median),
            RoundingMode::Down => $this->floorCard($cards, $median),
        };
    }

    /**
     * @param list<CardValue> $cards
     */
    private function nearestCard(array $cards, float $value): CardValue
    {
        $best = $cards[0];
        $bestNumeric = $best->numericValue() ?? 0.0;
        $bestDistance = abs($bestNumeric - $value);

        foreach ($cards as $card) {
            $numeric = $card->numericValue();
            if (null === $numeric) {
                continue;
            }

            $distance = abs($numeric - $value);
            if (
                $distance < $bestDistance - 0.001
                || (abs($distance - $bestDistance) < 0.001 && $numeric > $bestNumeric)
            ) {
                $best = $card;
                $bestNumeric = $numeric;
                $bestDistance = $distance;
            }
        }

        return $best;
    }

    /**
     * @param list<CardValue> $cards
     */
    private function ceilCard(array $cards, float $value): CardValue
    {
        foreach ($cards as $card) {
            $numeric = $card->numericValue();
            if (null !== $numeric && $numeric >= $value - 0.001) {
                return $card;
            }
        }

        return $cards[count($cards) - 1];
    }

    /**
     * @param list<CardValue> $cards
     */
    private function floorCard(array $cards, float $value): CardValue
    {
        $selected = $cards[0];

        foreach ($cards as $card) {
            $numeric = $card->numericValue();
            if (null !== $numeric && $numeric <= $value + 0.001) {
                $selected = $card;
            }
        }

        return $selected;
    }
}
