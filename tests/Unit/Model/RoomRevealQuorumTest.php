<?php

declare(strict_types=1);

namespace Symfinity\Bundle\PokerPlanner\Tests\Unit\Model;

use PHPUnit\Framework\TestCase;
use Symfinity\Bundle\PokerPlanner\Model\Participant;
use Symfinity\Bundle\PokerPlanner\Model\Phase;
use Symfinity\Bundle\PokerPlanner\Model\Room;
use Symfinity\Bundle\PokerPlanner\Model\StoryQueue;

final class RoomRevealQuorumTest extends TestCase
{
    public function testHasRevealQuorumRequiresHalfTheTable(): void
    {
        self::assertFalse($this->roomWithVotes(0, 1)->hasRevealQuorum());
        self::assertTrue($this->roomWithVotes(1, 1)->hasRevealQuorum());
        self::assertTrue($this->roomWithVotes(1, 2)->hasRevealQuorum());
        self::assertFalse($this->roomWithVotes(1, 3)->hasRevealQuorum());
        self::assertTrue($this->roomWithVotes(2, 3)->hasRevealQuorum());
    }

    public function testHasAnyVotes(): void
    {
        self::assertFalse($this->roomWithVotes(0, 2)->hasAnyVotes());
        self::assertTrue($this->roomWithVotes(1, 2)->hasAnyVotes());
    }

    private function roomWithVotes(int $voted, int $total): Room
    {
        $participants = [];
        for ($i = 0; $i < $total; ++$i) {
            $id = 'p'.$i;
            $participants[$id] = new Participant(
                id: $id,
                displayName: 'User '.$i,
                isModerator: 0 === $i,
                hasVoted: $i < $voted,
                voteValue: null,
                lastSeenAt: time(),
            );
        }

        return new Room(
            id: 'room-1',
            storyQueue: new StoryQueue(),
            phase: Phase::Voting,
            createdAt: time(),
            lastActivityAt: time(),
            participants: $participants,
        );
    }
}
