<?php

declare(strict_types=1);

namespace Symfinity\Bundle\PokerPlanner\Service;

use Symfinity\Bundle\PokerPlanner\Model\CardValue;
use Symfinity\Bundle\PokerPlanner\Model\ConsensusSummary;
use Symfinity\Bundle\PokerPlanner\Model\Room;

final class ConsensusCalculator
{
    public function __construct(
        private readonly DeckBuilder $deckBuilder,
    ) {
    }

    public function calculate(Room $room): ConsensusSummary
    {
        /** @var list<float> $numericVotes */
        $numericVotes = [];
        /** @var array<string, float> $participantValues */
        $participantValues = [];

        foreach ($room->participants as $participant) {
            $value = $participant->voteValue?->numericValue();
            if (null === $value) {
                continue;
            }

            $numericVotes[] = $value;
            $participantValues[$participant->id] = $value;
        }

        if ($numericVotes === []) {
            return new ConsensusSummary(0, null, null, []);
        }

        sort($numericVotes, SORT_NUMERIC);
        $median = $this->median($numericVotes);
        $spread = max($numericVotes) - min($numericVotes);
        $medianIndex = $this->deckBuilder->deckIndexForNumeric($room, $median);
        $outliers = [];

        if (null !== $medianIndex) {
            foreach ($participantValues as $participantId => $voteValue) {
                $voteIndex = $this->deckBuilder->deckIndexForNumeric($room, $voteValue);
                if (null === $voteIndex) {
                    continue;
                }

                if (abs($voteIndex - $medianIndex) > 1) {
                    $outliers[] = $participantId;
                }
            }
        }

        return new ConsensusSummary(
            count: count($numericVotes),
            median: $median,
            spread: $spread,
            outlierParticipantIds: $outliers,
        );
    }

    public function roundedMedianLabel(Room $room): ?string
    {
        $summary = $this->calculate($room);
        if (!$summary->hasNumericConsensus()) {
            return null;
        }

        $card = $this->deckBuilder->snapMedianToDeck(
            $room,
            (float) $summary->median,
            $room->settings->roundingMode,
        );

        return $card?->label();
    }

    /**
     * @param list<float> $values
     */
    private function median(array $values): float
    {
        $count = count($values);
        $middle = intdiv($count, 2);

        if (1 === $count % 2) {
            return $values[$middle];
        }

        return ($values[$middle - 1] + $values[$middle]) / 2;
    }
}
