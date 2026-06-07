<?php

declare(strict_types=1);

namespace Symfinity\Bundle\PokerPlanner\Tests\Unit\Model;

use PHPUnit\Framework\TestCase;
use Symfinity\Bundle\PokerPlanner\Model\CardValue;
use Symfinity\Bundle\PokerPlanner\Model\Participant;
use Symfinity\Bundle\PokerPlanner\Model\Phase;
use Symfinity\Bundle\PokerPlanner\Model\PublicParticipantView;

final class PublicParticipantViewTest extends TestCase
{
    public function testVoteLabelHiddenDuringVoting(): void
    {
        $participant = new Participant(
            id: 'p1',
            displayName: 'Ada',
            isModerator: false,
            hasVoted: true,
            voteValue: CardValue::Thirteen,
        );

        $view = PublicParticipantView::fromParticipant($participant, Phase::Voting);

        self::assertTrue($view->hasVoted);
        self::assertNull($view->voteLabel);
    }

    public function testVoteLabelVisibleAfterReveal(): void
    {
        $participant = new Participant(
            id: 'p1',
            displayName: 'Ada',
            isModerator: false,
            hasVoted: true,
            voteValue: CardValue::Half,
        );

        $view = PublicParticipantView::fromParticipant($participant, Phase::Revealed);

        self::assertSame('½', $view->voteLabel);
        self::assertSame('sky', $view->voteAccent);
    }
}
