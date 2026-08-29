<?php

declare(strict_types=1);

namespace Symfinity\Bundle\PokerPlanner\Tests\Unit\Template;

use PHPUnit\Framework\TestCase;
use Symfinity\Bundle\PokerPlanner\Model\ConsensusSummary;
use Symfinity\Bundle\PokerPlanner\Model\Phase;
use Symfinity\Bundle\PokerPlanner\Model\Room;
use Symfinity\Bundle\PokerPlanner\Model\StoryQueue;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Loader\ChainLoader;
use Twig\Loader\FilesystemLoader;

final class VoteUpdateStreamTest extends TestCase
{
    private Environment $twig;

    protected function setUp(): void
    {
        $stubs = new ArrayLoader([
            '@SymfinityPokerPlanner/room/_slot_grid.html.twig' => '<div id="slot-grid"></div>',
            '@SymfinityPokerPlanner/room/_vote_deck.html.twig' => '<section id="vote-deck"></section>',
            '@SymfinityPokerPlanner/room/_consensus_strip.html.twig' => '<div id="consensus-strip"></div>',
        ]);
        $files = new FilesystemLoader();
        $files->addPath(dirname(__DIR__, 3).'/templates', 'SymfinityPokerPlanner');
        $this->twig = new Environment(new ChainLoader([$stubs, $files]));
    }

    public function testVoteSwitchStreamSkipsSlotGridReplace(): void
    {
        $html = $this->twig->render('@SymfinityPokerPlanner/room/_vote_update.stream.html.twig', [
            'participants' => [],
            'phase' => Phase::Voting,
            'roomId' => 'room-1',
            'deck' => [],
            'room' => new Room(
                id: 'room-1',
                storyQueue: new StoryQueue(),
                phase: Phase::Voting,
                createdAt: time(),
                lastActivityAt: time(),
            ),
            'selectedVote' => '8',
            'refreshSlotGrid' => false,
            'consensus' => null,
        ]);

        self::assertStringNotContainsString('target="slot-grid"', $html);
        self::assertStringContainsString('target="vote-deck"', $html);
    }

    public function testRevealedVoteChangeStreamRefreshesSlotGrid(): void
    {
        $consensus = new ConsensusSummary(count: 2, median: 8.0, spread: 5.0, outlierParticipantIds: []);

        $html = $this->twig->render('@SymfinityPokerPlanner/room/_vote_update.stream.html.twig', [
            'participants' => [],
            'phase' => Phase::Revealed,
            'roomId' => 'room-1',
            'deck' => [],
            'room' => new Room(
                id: 'room-1',
                storyQueue: new StoryQueue(),
                phase: Phase::Revealed,
                createdAt: time(),
                lastActivityAt: time(),
            ),
            'selectedVote' => '13',
            'refreshSlotGrid' => true,
            'consensus' => $consensus,
            'roundedMedianLabel' => '8',
        ]);

        self::assertStringContainsString('target="slot-grid"', $html);
        self::assertStringContainsString('target="vote-deck"', $html);
        self::assertStringContainsString('target="consensus-strip"', $html);
        self::assertStringContainsString('target="pp-room-meta"', $html);
    }

    public function testFirstVoteStreamRefreshesSlotGrid(): void
    {
        $html = $this->twig->render('@SymfinityPokerPlanner/room/_vote_update.stream.html.twig', [
            'participants' => [],
            'phase' => Phase::Voting,
            'roomId' => 'room-1',
            'deck' => [],
            'room' => new Room(
                id: 'room-1',
                storyQueue: new StoryQueue(),
                phase: Phase::Voting,
                createdAt: time(),
                lastActivityAt: time(),
            ),
            'selectedVote' => '5',
            'refreshSlotGrid' => true,
            'consensus' => null,
        ]);

        self::assertStringContainsString('target="slot-grid"', $html);
        self::assertStringContainsString('target="vote-deck"', $html);
    }
}
