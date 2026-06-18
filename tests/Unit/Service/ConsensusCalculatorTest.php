<?php

declare(strict_types=1);

namespace Symfinity\Bundle\PokerPlanner\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use Symfinity\Bundle\PokerPlanner\Model\CardValue;
use Symfinity\Bundle\PokerPlanner\Model\Participant;
use Symfinity\Bundle\PokerPlanner\Model\Phase;
use Symfinity\Bundle\PokerPlanner\Model\Room;
use Symfinity\Bundle\PokerPlanner\Model\StoryQueue;
use Symfinity\Bundle\PokerPlanner\Service\ConsensusCalculator;
use Symfinity\Bundle\PokerPlanner\Service\DeckBuilder;

final class ConsensusCalculatorTest extends TestCase
{
    private ConsensusCalculator $calculator;

    protected function setUp(): void
    {
        $this->calculator = new ConsensusCalculator(new DeckBuilder());
    }

    public function testMedianAndSpread(): void
    {
        $room = $this->roomWithVotes([
            'a' => CardValue::Three,
            'b' => CardValue::Five,
            'c' => CardValue::Eight,
        ]);

        $summary = $this->calculator->calculate($room);

        self::assertSame(3, $summary->count);
        self::assertSame(5.0, $summary->median);
        self::assertSame(5.0, $summary->spread);
        self::assertFalse($summary->hasZeroSpread());
        self::assertSame('5', $summary->medianLabel());
    }

    public function testUnanimousVotesHaveZeroSpread(): void
    {
        $room = $this->roomWithVotes([
            'a' => CardValue::Five,
            'b' => CardValue::Five,
            'c' => CardValue::Five,
        ]);

        $summary = $this->calculator->calculate($room);

        self::assertSame(3, $summary->count);
        self::assertSame(5.0, $summary->median);
        self::assertSame(0.0, $summary->spread);
        self::assertTrue($summary->hasZeroSpread());
    }

    public function testNonNumericVotesExcluded(): void
    {
        $room = $this->roomWithVotes([
            'a' => CardValue::Unknown,
            'b' => CardValue::Coffee,
        ]);

        $summary = $this->calculator->calculate($room);

        self::assertSame(0, $summary->count);
        self::assertNull($summary->median);
        self::assertSame('No numeric consensus', $summary->statusMessage());
    }

    public function testOutliersBeyondOneFibonacciStep(): void
    {
        $room = $this->roomWithVotes([
            'a' => CardValue::Five,
            'b' => CardValue::Five,
            'far' => CardValue::Forty,
        ]);

        $summary = $this->calculator->calculate($room);

        self::assertContains('far', $summary->outlierParticipantIds);
        self::assertNotContains('a', $summary->outlierParticipantIds);
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
}
