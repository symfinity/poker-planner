<?php

declare(strict_types=1);

namespace Symfinity\Bundle\PokerPlanner\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use Symfinity\Bundle\PokerPlanner\Model\CardValue;
use Symfinity\Bundle\PokerPlanner\Model\Phase;
use Symfinity\Bundle\PokerPlanner\Model\Participant;
use Symfinity\Bundle\PokerPlanner\Model\Room;
use Symfinity\Bundle\PokerPlanner\Model\RoomSettings;
use Symfinity\Bundle\PokerPlanner\Model\RoundingMode;
use Symfinity\Bundle\PokerPlanner\Model\StoryQueue;
use Symfinity\Bundle\PokerPlanner\Service\ConsensusCalculator;
use Symfinity\Bundle\PokerPlanner\Service\DeckBuilder;

final class DeckBuilderTest extends TestCase
{
    private DeckBuilder $deckBuilder;

    private ConsensusCalculator $calculator;

    protected function setUp(): void
    {
        $this->deckBuilder = new DeckBuilder();
        $this->calculator = new ConsensusCalculator($this->deckBuilder);
    }

    public function testSnapMedianUsesDeckCardsForSplitVotes(): void
    {
        $room = $this->roomWithVotes([
            'a' => CardValue::Five,
            'b' => CardValue::Eight,
        ]);

        self::assertSame('8', $this->snap($room, RoundingMode::Nearest)?->label());
        self::assertSame('8', $this->snap($room, RoundingMode::Up)?->label());
        self::assertSame('5', $this->snap($room, RoundingMode::Down)?->label());
    }

    public function testRoundedMedianLabelRespectsRoomRoundingMode(): void
    {
        $room = $this->roomWithVotes([
            'a' => CardValue::Five,
            'b' => CardValue::Eight,
        ]);

        $room->settings = new RoomSettings(roundingMode: RoundingMode::Down);
        self::assertSame('5', $this->calculator->roundedMedianLabel($room));

        $room->settings = new RoomSettings(roundingMode: RoundingMode::Up);
        self::assertSame('8', $this->calculator->roundedMedianLabel($room));
    }

    /**
     * @param array<string, CardValue> $votes
     */
    private function roomWithVotes(array $votes): Room
    {
        $participants = [];
        foreach ($votes as $id => $card) {
            $participants[$id] = new Participant(
                id: $id,
                displayName: $id,
                isModerator: false,
                hasVoted: true,
                voteValue: $card,
            );
        }

        return new Room(
            id: 'room-1',
            storyQueue: new StoryQueue(),
            phase: Phase::Revealed,
            createdAt: time(),
            lastActivityAt: time(),
            participants: $participants,
        );
    }

    private function snap(Room $room, RoundingMode $mode): ?CardValue
    {
        return $this->deckBuilder->snapMedianToDeck($room, 6.5, $mode);
    }
}
