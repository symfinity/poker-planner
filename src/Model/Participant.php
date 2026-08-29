<?php

declare(strict_types=1);

namespace Symfinity\Bundle\PokerPlanner\Model;

use Symfinity\Bundle\PokerPlanner\Support\Coerce;

final class Participant
{
    public function __construct(
        public readonly string $id,
        public string $displayName,
        public readonly bool $isModerator,
        public bool $hasVoted = false,
        public ?CardValue $voteValue = null,
        public int $lastSeenAt = 0,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $vote = $data['voteValue'] ?? null;

        return new self(
            id: Coerce::string($data['id'] ?? null),
            displayName: Coerce::string($data['displayName'] ?? null),
            isModerator: Coerce::bool($data['isModerator'] ?? false),
            hasVoted: Coerce::bool($data['hasVoted'] ?? false),
            voteValue: is_string($vote) ? CardValue::from($vote) : null,
            lastSeenAt: Coerce::int($data['lastSeenAt'] ?? 0),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'displayName' => $this->displayName,
            'isModerator' => $this->isModerator,
            'hasVoted' => $this->hasVoted,
            'voteValue' => $this->voteValue?->value,
            'lastSeenAt' => $this->lastSeenAt,
        ];
    }
}
