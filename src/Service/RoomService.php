<?php

declare(strict_types=1);

namespace Symfinity\Bundle\PokerPlanner\Service;

use Symfinity\Bundle\PokerPlanner\Model\CardValue;
use Symfinity\Bundle\PokerPlanner\Model\ConsensusSummary;
use Symfinity\Bundle\PokerPlanner\Model\Participant;
use Symfinity\Bundle\PokerPlanner\Model\Phase;
use Symfinity\Bundle\PokerPlanner\Model\Room;
use Symfinity\Bundle\PokerPlanner\Model\RoomSettings;
use Symfinity\Bundle\PokerPlanner\Model\StoryQueue;
use Symfinity\Bundle\PokerPlanner\Storage\RoomStoreInterface;
use Symfony\Component\Uid\Uuid;

final class RoomService
{
    public function __construct(
        private readonly RoomStoreInterface $store,
        private readonly ConsensusCalculator $consensusCalculator,
        private readonly DeckBuilder $deckBuilder,
        private readonly int $maxTtlSeconds,
        private readonly int $savedTtlSeconds,
        private readonly int $graceSeconds,
    ) {
    }

    public function createRoom(string $moderatorName): Room
    {
        $now = time();
        $roomId = (string) Uuid::v4();
        $moderatorId = (string) Uuid::v4();

        $room = new Room(
            id: $roomId,
            storyQueue: new StoryQueue(),
            phase: Phase::Voting,
            createdAt: $now,
            lastActivityAt: $now,
            participants: [
                $moderatorId => new Participant(
                    id: $moderatorId,
                    displayName: $moderatorName,
                    isModerator: true,
                    lastSeenAt: $now,
                ),
            ],
        );

        $this->persist($room);

        return $room;
    }

    /**
     * @return array{room: Room, participantId: string}
     */
    public function joinRoom(string $roomId, string $displayName): array
    {
        $room = $this->requireRoom($roomId);
        $this->garbageCollect($room);

        $now = time();
        $participantId = (string) Uuid::v4();
        $room->participants[$participantId] = new Participant(
            id: $participantId,
            displayName: $displayName,
            isModerator: false,
            lastSeenAt: $now,
        );
        $room->touch($now);
        $this->persist($room);

        return ['room' => $room, 'participantId' => $participantId];
    }

    public function vote(string $roomId, string $participantId, CardValue $card): Room
    {
        $room = $this->requireRoom($roomId);
        $participant = $this->requireParticipant($room, $participantId);

        if (!$this->isVotingOpen($room)) {
            throw new \DomainException('Voting is closed.');
        }

        if (!$this->isCardInDeck($room, $card)) {
            throw new \InvalidArgumentException('Card is not in the active deck.');
        }

        $participant->hasVoted = true;
        $participant->voteValue = $card;
        $room->touch(time());
        $this->persist($room);

        return $room;
    }

    public function clearVote(string $roomId, string $participantId): Room
    {
        $room = $this->requireRoom($roomId);
        $participant = $this->requireParticipant($room, $participantId);

        if (!$this->isVotingOpen($room)) {
            throw new \DomainException('Voting is closed.');
        }

        $participant->hasVoted = false;
        $participant->voteValue = null;
        $room->touch(time());
        $this->persist($room);

        return $room;
    }

    public function reveal(string $roomId, string $participantId): Room
    {
        $room = $this->requireRoom($roomId);
        $this->requireModerator($room, $participantId);

        if ($room->storyQueue->complete) {
            throw new \DomainException('Queue is complete.');
        }

        if ($room->phase === Phase::Revealed) {
            throw new \DomainException('Cards are already revealed.');
        }

        if (!$room->hasRevealQuorum()) {
            throw new \DomainException('At least half the table must vote before revealing.');
        }

        $room->phase = Phase::Revealed;
        $room->touch(time());
        $this->persist($room);

        return $room;
    }

    public function restart(string $roomId, string $participantId): Room
    {
        $room = $this->requireRoom($roomId);
        $this->requireModerator($room, $participantId);

        if ($room->storyQueue->complete) {
            throw new \DomainException('Queue is complete.');
        }

        if (!$room->hasAnyVotes()) {
            throw new \DomainException('No votes to restart.');
        }

        $this->clearVotes($room);
        $room->phase = Phase::Voting;
        $room->touch(time());
        $this->persist($room);

        return $room;
    }

    public function setStoryTitle(string $roomId, string $participantId, string $title): Room
    {
        $room = $this->requireRoom($roomId);
        $this->requireModerator($room, $participantId);

        $room->storyQueue->setCurrentTitle($title);
        $room->touch(time());
        $this->persist($room);

        return $room;
    }

    public function renameParticipant(string $roomId, string $participantId, string $displayName): Room
    {
        $room = $this->requireRoom($roomId);
        $participant = $this->requireParticipant($room, $participantId);

        $displayName = trim($displayName);
        if ('' === $displayName) {
            throw new \InvalidArgumentException('Display name is required.');
        }

        $participant->displayName = $displayName;
        $room->touch(time());
        $this->persist($room);

        return $room;
    }

    public function addStory(string $roomId, string $participantId, string $title): Room
    {
        $room = $this->requireRoom($roomId);
        $this->requireModerator($room, $participantId);

        $room->storyQueue->addStory($title);
        $room->touch(time());
        $this->persist($room);

        return $room;
    }

    public function removeStory(string $roomId, string $participantId, int $index): Room
    {
        $room = $this->requireRoom($roomId);
        $this->requireModerator($room, $participantId);

        $room->storyQueue->removeStory($index);
        $room->touch(time());
        $this->persist($room);

        return $room;
    }

    public function nextStory(string $roomId, string $participantId, ?string $recordedEstimate = null): Room
    {
        $room = $this->requireRoom($roomId);
        $this->requireModerator($room, $participantId);

        if ($room->storyQueue->complete) {
            throw new \DomainException('Queue is already complete.');
        }

        if ($room->phase !== Phase::Revealed) {
            throw new \DomainException('Reveal the current round before advancing.');
        }

        if ($room->storyQueue->count() === 0) {
            throw new \DomainException('Add at least one story before continuing.');
        }

        $estimate = $recordedEstimate ?? $this->defaultRecordedEstimate($room) ?? '?';
        $room->storyQueue->recordCurrentEstimate($estimate);

        if ($room->storyQueue->isAtEnd()) {
            $room->storyQueue->markComplete();
            $room->touch(time());
            $this->persist($room);

            return $room;
        }

        $room->storyQueue->advance();

        $this->clearVotes($room);
        $room->phase = Phase::Voting;
        $room->touch(time());
        $this->persist($room);

        return $room;
    }

    public function startNewSession(string $roomId, string $participantId): Room
    {
        $room = $this->requireRoom($roomId);
        $this->requireModerator($room, $participantId);

        if ($room->storyQueue->count() > 0) {
            throw new \DomainException('Finish the queue before starting a new session.');
        }

        $room->storyQueue->archiveAndStartNewSession();
        $this->clearVotes($room);
        $room->phase = Phase::Voting;
        $room->touch(time());
        $this->persist($room);

        return $room;
    }

    public function consensus(Room $room): ConsensusSummary
    {
        return $this->consensusCalculator->calculate($room);
    }

    public function roundedMedianLabel(Room $room): ?string
    {
        return $this->consensusCalculator->roundedMedianLabel($room);
    }

    public function heartbeat(string $roomId, string $participantId): Room
    {
        $room = $this->requireRoom($roomId);
        $participant = $this->requireParticipant($room, $participantId);

        $now = time();
        $participant->lastSeenAt = $now;
        $room->touch($now);

        $this->garbageCollect($room);
        $this->persist($room);

        return $room;
    }

    public function updateRoomSettings(string $roomId, string $participantId, RoomSettings $settings): Room
    {
        $room = $this->requireRoom($roomId);
        $this->requireModerator($room, $participantId);

        $room->settings = $settings;
        $this->sanitizeVotesForDeck($room);
        $room->touch(time());
        $this->persist($room);

        return $room;
    }

    public function saveRoom(string $roomId, string $participantId, string $teamName): Room
    {
        $room = $this->requireRoom($roomId);
        $this->requireModerator($room, $participantId);

        $teamName = trim($teamName);
        if ('' === $teamName) {
            throw new \InvalidArgumentException('Team name is required.');
        }

        $room->settings->teamName = $teamName;
        $room->settings->saved = true;
        $room->touch(time());
        $this->persist($room);

        return $room;
    }

    public function deleteRoom(string $roomId, string $participantId): void
    {
        $room = $this->requireRoom($roomId);
        $this->requireModerator($room, $participantId);

        $this->store->delete($roomId);
    }

    public function getRoom(string $roomId): ?Room
    {
        $room = $this->store->get($roomId);
        if (!$room instanceof Room) {
            return null;
        }

        $this->garbageCollect($room);

        return $this->store->get($roomId);
    }

    public function remainingTtl(Room $room): int
    {
        $limit = $room->settings->saved ? $this->savedTtlSeconds : $this->maxTtlSeconds;
        $elapsed = time() - $room->createdAt;

        return max(1, $limit - $elapsed);
    }

    private function isVotingOpen(Room $room): bool
    {
        if ($room->storyQueue->complete) {
            return false;
        }

        return match ($room->phase) {
            Phase::Voting => true,
            Phase::Revealed => $room->settings->allowChangeAfterReveal,
        };
    }

    private function isCardInDeck(Room $room, CardValue $card): bool
    {
        foreach ($this->deckBuilder->buildForRoom($room) as $deckCard) {
            if ($deckCard === $card) {
                return true;
            }
        }

        return false;
    }

    private function sanitizeVotesForDeck(Room $room): void
    {
        foreach ($room->participants as $participant) {
            if (!$participant->voteValue instanceof CardValue) {
                continue;
            }

            if (!$this->isCardInDeck($room, $participant->voteValue)) {
                $participant->hasVoted = false;
                $participant->voteValue = null;
            }
        }
    }

    private function defaultRecordedEstimate(Room $room): ?string
    {
        return $this->consensusCalculator->roundedMedianLabel($room);
    }

    private function clearVotes(Room $room): void
    {
        foreach ($room->participants as $participant) {
            $participant->hasVoted = false;
            $participant->voteValue = null;
        }
    }

    private function persist(Room $room): void
    {
        $this->store->save($room, $this->remainingTtl($room));
    }

    private function garbageCollect(Room $room): void
    {
        $now = time();
        $limit = $room->settings->saved ? $this->savedTtlSeconds : $this->maxTtlSeconds;
        if ($now - $room->createdAt >= $limit) {
            $this->store->delete($room->id);
            throw new \DomainException('Room expired.');
        }

        $active = array_filter(
            $room->participants,
            fn (Participant $p): bool => ($now - $p->lastSeenAt) <= $this->graceSeconds,
        );

        if ([] === $active && [] !== $room->participants) {
            $this->store->delete($room->id);
            throw new \DomainException('Room closed.');
        }
    }

    private function requireRoom(string $roomId): Room
    {
        $room = $this->store->get($roomId);
        if (!$room instanceof Room) {
            throw new \DomainException('Room not found.');
        }

        return $room;
    }

    private function requireParticipant(Room $room, string $participantId): Participant
    {
        $participant = $room->findParticipant($participantId);
        if (!$participant instanceof Participant) {
            throw new \DomainException('Participant not found.');
        }

        return $participant;
    }

    private function requireModerator(Room $room, string $participantId): Participant
    {
        $participant = $this->requireParticipant($room, $participantId);
        if (!$participant->isModerator) {
            throw new \DomainException('Moderator only.');
        }

        return $participant;
    }
}
