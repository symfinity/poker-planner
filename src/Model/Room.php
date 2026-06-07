<?php

declare(strict_types=1);

namespace Symfinity\Bundle\PokerPlanner\Model;

final class Room
{
    /**
     * @param array<string, Participant> $participants
     */
    public function __construct(
        public readonly string $id,
        public string $storyTitle,
        public Phase $phase,
        public readonly int $createdAt,
        public int $lastActivityAt,
        public array $participants = [],
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $participants = [];
        foreach ($data['participants'] ?? [] as $id => $row) {
            if (!is_array($row)) {
                continue;
            }
            $row['id'] ??= (string) $id;
            $participants[(string) $id] = Participant::fromArray($row);
        }

        return new self(
            id: (string) $data['id'],
            storyTitle: (string) ($data['storyTitle'] ?? ''),
            phase: Phase::from((string) ($data['phase'] ?? Phase::Voting->value)),
            createdAt: (int) ($data['createdAt'] ?? time()),
            lastActivityAt: (int) ($data['lastActivityAt'] ?? time()),
            participants: $participants,
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
            'id' => $this->id,
            'storyTitle' => $this->storyTitle,
            'phase' => $this->phase->value,
            'createdAt' => $this->createdAt,
            'lastActivityAt' => $this->lastActivityAt,
            'participants' => $participants,
        ];
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

    public function touch(int $now): void
    {
        $this->lastActivityAt = $now;
    }
}
