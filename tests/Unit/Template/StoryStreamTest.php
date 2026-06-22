<?php

declare(strict_types=1);

namespace Symfinity\Bundle\PokerPlanner\Tests\Unit\Template;

use PHPUnit\Framework\TestCase;
use Symfinity\Bundle\PokerPlanner\Model\Phase;
use Symfinity\Bundle\PokerPlanner\Model\Room;
use Symfinity\Bundle\PokerPlanner\Model\StoryQueue;
use Symfinity\Bundle\PokerPlanner\Model\StoryQueueItem;
use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\Loader\ArrayLoader;
use Twig\Loader\ChainLoader;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;

final class StoryStreamTest extends TestCase
{
    private Environment $twig;

    protected function setUp(): void
    {
        $stubs = new ArrayLoader([
            '@SymfinityPokerPlanner/room/_story_title.stream.html.twig' => '',
            '@SymfinityPokerPlanner/room/_story_queue_list.stream.html.twig' => '',
            '@SymfinityPokerPlanner/room/_story_queue_sync.stream.html.twig' => '',
            '@SymfinityPokerPlanner/room/_story_queue_moderator.html.twig' => '<div id="story-queue-moderator"></div>',
        ]);
        $files = new FilesystemLoader();
        $files->addPath(dirname(__DIR__, 3).'/templates', 'SymfinityPokerPlanner');
        $this->twig = new Environment(new ChainLoader([$stubs, $files]));
        $this->twig->addExtension(new class extends AbstractExtension {
            public function getFunctions(): array
            {
                return [
                    new TwigFunction('path', static fn (string $name): string => '/'.$name),
                ];
            }
        });
    }

    public function testModeratorStoryStreamRefreshesModeratorActions(): void
    {
        $queue = new StoryQueue(
            items: [
                new StoryQueueItem('Story one'),
                new StoryQueueItem('Story two'),
                new StoryQueueItem('Story three'),
            ],
        );

        $html = $this->twig->render('@SymfinityPokerPlanner/room/_story.stream.html.twig', [
            'storyTitle' => 'Story one',
            'storyQueue' => $queue,
            'roomId' => 'room-1',
            'room' => new Room(
                id: 'room-1',
                storyQueue: $queue,
                phase: Phase::Voting,
                createdAt: time(),
                lastActivityAt: time(),
            ),
            'phase' => Phase::Voting,
            'isModerator' => true,
            'canRevealQuorum' => false,
            'hasAnyVotes' => false,
        ]);

        self::assertStringContainsString('target="moderator-actions"', $html);
        self::assertMatchesRegularExpression('/pp-btn--primary[^>]*disabled/', $html);
    }

    public function testParticipantStoryStreamSkipsModeratorActions(): void
    {
        $html = $this->twig->render('@SymfinityPokerPlanner/room/_story.stream.html.twig', [
            'storyTitle' => 'Story one',
            'storyQueue' => new StoryQueue(items: [new StoryQueueItem('Story one')]),
            'roomId' => 'room-1',
            'room' => new Room(
                id: 'room-1',
                storyQueue: new StoryQueue(items: [new StoryQueueItem('Story one')]),
                phase: Phase::Voting,
                createdAt: time(),
                lastActivityAt: time(),
            ),
            'phase' => Phase::Voting,
            'isModerator' => false,
        ]);

        self::assertStringNotContainsString('target="moderator-actions"', $html);
    }
}
