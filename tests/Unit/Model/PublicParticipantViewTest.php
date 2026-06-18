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
        self::assertNull($view->voteIcon);
    }

    public function testVoteIconVisibleForPassAndBreak(): void
    {
        $pass = PublicParticipantView::fromParticipant(
            new Participant('p1', 'Ada', false, true, CardValue::Pass),
            Phase::Revealed,
        );
        self::assertNull($pass->voteLabel);
        self::assertSame('pass', $pass->voteIcon);

        $break = PublicParticipantView::fromParticipant(
            new Participant('p2', 'Bob', false, true, CardValue::Break),
            Phase::Revealed,
        );
        self::assertSame('coffee', $break->voteIcon);
    }
}
