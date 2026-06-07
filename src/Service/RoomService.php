<?php

declare(strict_types=1);

namespace Symfinity\Bundle\PokerPlanner\Service;

use Symfinity\Bundle\PokerPlanner\Model\CardValue;
use Symfinity\Bundle\PokerPlanner\Model\Participant;
use Symfinity\Bundle\PokerPlanner\Model\Phase;
use Symfinity\Bundle\PokerPlanner\Model\Room;
use Symfinity\Bundle\PokerPlanner\Storage\RoomStoreInterface;
use Symfony\Component\Uid\Uuid;

final class RoomService
{
    public function __construct(
        private readonly RoomStoreInterface $store,
        private readonly int $maxTtlSeconds,
        private readonly int $graceSeconds,
        private readonly int $heartbeatSeconds,
    ) {
    }

    public function createRoom(string $moderatorName): Room
    {
        $now = time();
        $roomId = (string) Uuid::v4();
        $moderatorId = (string) Uuid::v4();

        $room = new Room(
            id: $roomId,
            storyTitle: '',
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

        if ($room->phase !== Phase::Voting) {
            throw new \DomainException('Voting is closed.');
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

        if ($room->phase !== Phase::Voting) {
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

        $room->phase = Phase::Revealed;
        $room->touch(time());
        $this->persist($room);

        return $room;
    }

    public function restart(string $roomId, string $participantId): Room
    {
        $room = $this->requireRoom($roomId);
        $this->requireModerator($room, $participantId);

        foreach ($room->participants as $participant) {
            $participant->hasVoted = false;
            $participant->voteValue = null;
        }

        $room->phase = Phase::Voting;
        $room->touch(time());
        $this->persist($room);

        return $room;
    }

    public function setStoryTitle(string $roomId, string $participantId, string $title): Room
    {
        $room = $this->requireRoom($roomId);
        $this->requireModerator($room, $participantId);

        $room->storyTitle = trim($title);
        $room->touch(time());
        $this->persist($room);

        return $room;
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
        $elapsed = time() - $room->createdAt;

        return max(1, $this->maxTtlSeconds - $elapsed);
    }

    private function persist(Room $room): void
    {
        $this->store->save($room, $this->remainingTtl($room));
    }

    private function garbageCollect(Room $room): void
    {
        $now = time();
        if ($now - $room->createdAt >= $this->maxTtlSeconds) {
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
