<?php

declare(strict_types=1);

namespace Symfinity\Bundle\PokerPlanner\Model;

/**
 * Client-safe participant projection — vote value only when revealed.
 */
final class PublicParticipantView
{
    public function __construct(
        public readonly string $id,
        public readonly string $displayName,
        public readonly bool $isModerator,
        public readonly bool $hasVoted,
        public readonly ?string $voteLabel,
        public readonly ?string $voteAccent = null,
    ) {
    }

    public static function fromParticipant(Participant $participant, Phase $phase): self
    {
        $voteLabel = null;
        $voteAccent = null;
        if ($phase === Phase::Revealed && $participant->voteValue instanceof CardValue) {
            $voteLabel = $participant->voteValue->label();
            $voteAccent = $participant->voteValue->accent();
        }

        return new self(
            id: $participant->id,
            displayName: $participant->displayName,
            isModerator: $participant->isModerator,
            hasVoted: $participant->hasVoted,
            voteLabel: $voteLabel,
            voteAccent: $voteAccent,
        );
    }
}
