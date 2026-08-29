<?php

declare(strict_types=1);

namespace Symfinity\Bundle\PokerPlanner\Model;

use Symfinity\Bundle\PokerPlanner\Support\Coerce;

final class Room
{
    public const SCHEMA_VERSION = 3;

    /**
     * @param array<string, Participant> $participants
     */
    public function __construct(
        public readonly string $id,
        public StoryQueue $storyQueue,
        public Phase $phase,
        public readonly int $createdAt,
        public int $lastActivityAt,
        public array $participants = [],
        public ?string $externalRef = null,
        public RoomSettings $settings = new RoomSettings(),
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $participants = [];
        foreach (Coerce::arrayMap($data['participants'] ?? null) as $id => $row) {
            if (!is_array($row)) {
                continue;
            }
            /** @var array<string, mixed> $row */
            $row['id'] ??= Coerce::string($id);
            $participants[Coerce::string($id)] = Participant::fromArray($row);
        }

        $schemaVersion = Coerce::int($data['schemaVersion'] ?? 1, 1);
        $storyQueueRaw = $data['storyQueue'] ?? null;
        if (is_array($storyQueueRaw)) {
            /** @var array<string, mixed> $storyQueueRaw */
            $storyQueue = StoryQueue::fromArray($storyQueueRaw);
        } elseif ($schemaVersion < 2) {
            $storyQueue = StoryQueue::fromLegacyTitle(Coerce::string($data['storyTitle'] ?? null));
        } else {
            $storyQueue = new StoryQueue();
        }

        $settingsRaw = $data['settings'] ?? null;
        if (is_array($settingsRaw)) {
            /** @var array<string, mixed> $settingsRaw */
            $settings = RoomSettings::fromArray($settingsRaw);
        } else {
            $settings = new RoomSettings();
        }

        $externalRef = $data['externalRef'] ?? null;

        return new self(
            id: Coerce::string($data['id'] ?? null),
            storyQueue: $storyQueue,
            phase: Phase::from(Coerce::string($data['phase'] ?? Phase::Voting->value, Phase::Voting->value)),
            createdAt: Coerce::int($data['createdAt'] ?? time(), time()),
            lastActivityAt: Coerce::int($data['lastActivityAt'] ?? time(), time()),
            participants: $participants,
            externalRef: is_string($externalRef) ? $externalRef : null,
            settings: $settings,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $participants = [];
        foreach ($this->participants as $id => $participant) {
            $participants[$id] = $participant->toArray();
        }

        return [
            'schemaVersion' => self::SCHEMA_VERSION,
            'id' => $this->id,
            'storyQueue' => $this->storyQueue->toArray(),
            'storyTitle' => $this->getStoryTitle(),
            'phase' => $this->phase->value,
            'createdAt' => $this->createdAt,
            'lastActivityAt' => $this->lastActivityAt,
            'participants' => $participants,
            'externalRef' => $this->externalRef,
            'settings' => $this->settings->toArray(),
        ];
    }

    public function getStoryTitle(): string
    {
        return $this->storyQueue->currentTitle();
    }

    public function findParticipant(string $participantId): ?Participant
    {
        return $this->participants[$participantId] ?? null;
    }

    public function moderator(): ?Participant
    {
        foreach ($this->participants as $participant) {
            if ($participant->isModerator) {
                return $participant;
            }
        }

        return null;
    }

    public function participantCount(): int
    {
        return count($this->participants);
    }

    public function votedParticipantCount(): int
    {
        $count = 0;
        foreach ($this->participants as $participant) {
            if ($participant->hasVoted) {
                ++$count;
            }
        }

        return $count;
    }

    public function hasRevealQuorum(): bool
    {
        $total = $this->participantCount();
        if ($total === 0) {
            return false;
        }

        return $this->votedParticipantCount() * 2 >= $total;
    }

    public function hasAnyVotes(): bool
    {
        return $this->votedParticipantCount() > 0;
    }

    public function touch(int $now): void
    {
        $this->lastActivityAt = $now;
    }
}
