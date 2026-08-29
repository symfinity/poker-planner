<?php

declare(strict_types=1);

namespace Symfinity\Bundle\PokerPlanner\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use Symfinity\Bundle\PokerPlanner\Model\CardValue;
use Symfinity\Bundle\PokerPlanner\Model\Phase;
use Symfinity\Bundle\PokerPlanner\Service\ConsensusCalculator;
use Symfinity\Bundle\PokerPlanner\Service\DeckBuilder;
use Symfinity\Bundle\PokerPlanner\Service\RoomService;
use Symfinity\Bundle\PokerPlanner\Storage\InMemoryRoomStore;

final class RoomServiceTest extends TestCase
{
    private RoomService $service;

    protected function setUp(): void
    {
        $this->service = new RoomService(
            new InMemoryRoomStore(),
            new ConsensusCalculator(new DeckBuilder()),
            new DeckBuilder(),
            14_400,
            31_536_000,
            600,
        );
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

    public function testRevealRequiresHalfTheTable(): void
    {
        $room = $this->service->createRoom('Mod');
        $moderator = $room->moderator();
        self::assertNotNull($moderator);

        $dev1 = $this->service->joinRoom($room->id, 'Dev1')['participantId'];
        $this->service->joinRoom($room->id, 'Dev2');
        $this->service->vote($room->id, $dev1, CardValue::Five);

        $this->expectException(\DomainException::class);
        $this->service->reveal($room->id, $moderator->id);
    }

    public function testRestartRequiresAtLeastOneVote(): void
    {
        $room = $this->service->createRoom('Mod');
        $moderator = $room->moderator();
        self::assertNotNull($moderator);
        $this->service->joinRoom($room->id, 'Dev');

        $this->expectException(\DomainException::class);
        $this->service->restart($room->id, $moderator->id);
    }

    public function testStoryQueueNextStoryRecordsMedian(): void
    {
        $room = $this->service->createRoom('Mod');
        $moderator = $room->moderator();
        self::assertNotNull($moderator);

        $this->service->addStory($room->id, $moderator->id, 'Story one');
        $this->service->addStory($room->id, $moderator->id, 'Story two');

        $join = $this->service->joinRoom($room->id, 'Dev');
        $this->service->vote($room->id, $join['participantId'], CardValue::Three);
        $this->service->vote($room->id, $moderator->id, CardValue::Five);
        $dev2 = $this->service->joinRoom($room->id, 'Dev2');
        $this->service->vote($room->id, $dev2['participantId'], CardValue::Eight);
        $this->service->reveal($room->id, $moderator->id);

        $advanced = $this->service->nextStory($room->id, $moderator->id);
        self::assertSame(Phase::Voting, $advanced->phase);
        self::assertSame('Story two', $advanced->getStoryTitle());
        self::assertSame('5', $advanced->storyQueue->items[0]->recordedEstimate);
    }

    public function testFinishQueueOnLastStory(): void
    {
        $room = $this->service->createRoom('Mod');
        $moderator = $room->moderator();
        self::assertNotNull($moderator);

        $this->service->addStory($room->id, $moderator->id, 'Final story');
        $join = $this->service->joinRoom($room->id, 'Dev');
        $this->service->vote($room->id, $join['participantId'], CardValue::Five);
        $this->service->vote($room->id, $moderator->id, CardValue::Five);
        $this->service->reveal($room->id, $moderator->id);

        $finished = $this->service->nextStory($room->id, $moderator->id);
        self::assertFalse($finished->storyQueue->complete);
        self::assertSame(Phase::Revealed, $finished->phase);
        self::assertSame(0, $finished->storyQueue->count());
        self::assertCount(1, $finished->storyQueue->recapRows());
        self::assertSame('5', $finished->storyQueue->recapRows()[0]['estimate']);
    }

    public function testStartNewSessionAfterQueueComplete(): void
    {
        $room = $this->service->createRoom('Mod');
        $moderator = $room->moderator();
        self::assertNotNull($moderator);

        $this->service->addStory($room->id, $moderator->id, 'Story one');
        $join = $this->service->joinRoom($room->id, 'Dev');
        $this->service->vote($room->id, $join['participantId'], CardValue::Three);
        $this->service->vote($room->id, $moderator->id, CardValue::Three);
        $this->service->reveal($room->id, $moderator->id);
        $finished = $this->service->nextStory($room->id, $moderator->id);

        self::assertFalse($finished->storyQueue->complete);
        self::assertSame(0, $finished->storyQueue->count());

        $restarted = $this->service->startNewSession($room->id, $moderator->id);
        self::assertFalse($restarted->storyQueue->complete);
        self::assertSame(Phase::Voting, $restarted->phase);
        self::assertSame(0, $restarted->storyQueue->count());
        self::assertCount(1, $restarted->storyQueue->recapRows());
        self::assertFalse($restarted->findParticipant($join['participantId'])?->hasVoted);
    }

    public function testVoteAfterRevealWhenAllowChangeEnabled(): void
    {
        $room = $this->service->createRoom('Mod');
        $moderator = $room->moderator();
        self::assertNotNull($moderator);
        $join = $this->service->joinRoom($room->id, 'Dev');
        $devId = $join['participantId'];

        $settings = $room->settings;
        $settings->allowChangeAfterReveal = true;
        $this->service->updateRoomSettings($room->id, $moderator->id, $settings);

        $this->service->vote($room->id, $devId, CardValue::Eight);
        $this->service->reveal($room->id, $moderator->id);

        $changed = $this->service->vote($room->id, $devId, CardValue::Thirteen);
        self::assertSame(CardValue::Thirteen, $changed->findParticipant($devId)?->voteValue);
    }

    public function testVoteAfterRevealRejectedWhenAllowChangeDisabled(): void
    {
        $room = $this->service->createRoom('Mod');
        $moderator = $room->moderator();
        self::assertNotNull($moderator);
        $join = $this->service->joinRoom($room->id, 'Dev');
        $devId = $join['participantId'];

        $this->service->vote($room->id, $devId, CardValue::Eight);
        $this->service->reveal($room->id, $moderator->id);

        $this->expectException(\DomainException::class);
        $this->service->vote($room->id, $devId, CardValue::Thirteen);
    }

    public function testLegacyStoryTitleMigratesOnLoad(): void
    {
        $store = new InMemoryRoomStore();
        $deckBuilder = new DeckBuilder();
        $service = new RoomService(
            $store,
            new ConsensusCalculator($deckBuilder),
            $deckBuilder,
            14_400,
            31_536_000,
            600,
        );
        $room = $service->createRoom('Mod');
        $moderator = $room->moderator();
        self::assertNotNull($moderator);

        $room->storyQueue->setCurrentTitle('Legacy title');
        $store->save($room, 3600);

        $raw = $store->get($room->id);
        self::assertNotNull($raw);
        self::assertSame('Legacy title', $raw->getStoryTitle());
        self::assertSame(3, $raw->toArray()['schemaVersion']);
    }
}
