<?php

declare(strict_types=1);

namespace Symfinity\Bundle\PokerPlanner\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use Symfinity\Bundle\PokerPlanner\Model\CardValue;
use Symfinity\Bundle\PokerPlanner\Model\Phase;
use Symfinity\Bundle\PokerPlanner\Service\RoomService;
use Symfinity\Bundle\PokerPlanner\Storage\InMemoryRoomStore;

final class RoomServiceTest extends TestCase
{
    private RoomService $service;

    protected function setUp(): void
    {
        $this->service = new RoomService(new InMemoryRoomStore(), 14_400, 600, 30);
    }

    public function testCreateRevealAndRestart(): void
    {
        $room = $this->service->createRoom('Mod');
        $moderator = $room->moderator();
        self::assertNotNull($moderator);

        $join = $this->service->joinRoom($room->id, 'Dev');
        $devId = $join['participantId'];

        $this->service->vote($room->id, $devId, CardValue::Eight);
        $revealed = $this->service->reveal($room->id, $moderator->id);
        self::assertSame(Phase::Revealed, $revealed->phase);

        $restarted = $this->service->restart($room->id, $moderator->id);
        self::assertSame(Phase::Voting, $restarted->phase);
        self::assertFalse($restarted->findParticipant($devId)?->hasVoted);

        $this->service->vote($room->id, $devId, CardValue::Five);
        $revealedAgain = $this->service->reveal($room->id, $moderator->id);
        self::assertSame(Phase::Revealed, $revealedAgain->phase);
    }

    public function testClearVoteRemovesParticipantVote(): void
    {
        $room = $this->service->createRoom('Mod');
        $join = $this->service->joinRoom($room->id, 'Dev');
        $devId = $join['participantId'];

        $voted = $this->service->vote($room->id, $devId, CardValue::Eight);
        self::assertTrue($voted->findParticipant($devId)?->hasVoted);

        $cleared = $this->service->clearVote($room->id, $devId);
        $participant = $cleared->findParticipant($devId);
        self::assertNotNull($participant);
        self::assertFalse($participant->hasVoted);
        self::assertNull($participant->voteValue);
    }

    public function testNonModeratorCannotReveal(): void
    {
        $room = $this->service->createRoom('Mod');
        $join = $this->service->joinRoom($room->id, 'Dev');

        $this->expectException(\DomainException::class);
        $this->service->reveal($room->id, $join['participantId']);
    }
}
