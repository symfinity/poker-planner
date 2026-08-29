<?php

declare(strict_types=1);

namespace Symfinity\Bundle\PokerPlanner\Tests\Unit\Template;

use PHPUnit\Framework\TestCase;
use Symfinity\Bundle\PokerPlanner\Model\Phase;
use Symfinity\Bundle\PokerPlanner\Model\StoryQueue;
use Symfinity\Bundle\PokerPlanner\Model\StoryQueueItem;
use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\Loader\ArrayLoader;
use Twig\Loader\ChainLoader;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;

final class ModeratorActionsTemplateTest extends TestCase
{
    private Environment $twig;

    protected function setUp(): void
    {
        $stubs = new ArrayLoader([]);
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

    public function testAdvanceStoryButtonDisabledBeforeReveal(): void
    {
        $html = $this->renderActions(Phase::Voting, new StoryQueue(items: [
            new StoryQueueItem('Story one'),
            new StoryQueueItem('Story two'),
        ]));

        self::assertStringContainsString('Next story', $html);
        self::assertMatchesRegularExpression('/pp-btn--primary[^>]*disabled/', $html);
    }

    public function testAdvanceStoryButtonEnabledAfterReveal(): void
    {
        $html = $this->renderActions(Phase::Revealed, new StoryQueue(items: [
            new StoryQueueItem('Story one'),
            new StoryQueueItem('Story two'),
        ]));

        self::assertStringContainsString('Next story', $html);
        self::assertDoesNotMatchRegularExpression('/pp-btn--primary[^>]*disabled/', $html);
    }

    public function testFinishQueueButtonEnabledOnLastStoryAfterReveal(): void
    {
        $queue = new StoryQueue(items: [new StoryQueueItem('Only story')]);
        $html = $this->renderActions(Phase::Revealed, $queue);

        self::assertStringContainsString('Finish queue', $html);
        self::assertDoesNotMatchRegularExpression('/pp-btn--primary[^>]*disabled/', $html);
    }

    public function testRevealButtonDisabledWithoutQuorum(): void
    {
        $html = $this->renderActions(Phase::Voting, new StoryQueue(items: [
            new StoryQueueItem('Story one'),
        ]), canRevealQuorum: false);

        self::assertMatchesRegularExpression('/pp-btn--reveal[^>]*disabled/', $html);
    }

    public function testRevealButtonEnabledWithQuorum(): void
    {
        $html = $this->renderActions(Phase::Voting, new StoryQueue(items: [
            new StoryQueueItem('Story one'),
        ]), canRevealQuorum: true);

        self::assertDoesNotMatchRegularExpression('/pp-btn--reveal[^>]*disabled/', $html);
    }

    public function testRevealButtonDisabledAfterReveal(): void
    {
        $html = $this->renderActions(Phase::Revealed, new StoryQueue(items: [
            new StoryQueueItem('Story one'),
        ]), canRevealQuorum: true, hasAnyVotes: true);

        self::assertMatchesRegularExpression('/pp-btn--reveal[^>]*disabled/', $html);
    }

    public function testRestartButtonDisabledWithoutVotes(): void
    {
        $html = $this->renderActions(Phase::Voting, new StoryQueue(items: [
            new StoryQueueItem('Story one'),
        ]), hasAnyVotes: false);

        self::assertMatchesRegularExpression('/pp-btn--outline[^>]*disabled/', $html);
    }

    public function testRestartButtonEnabledWithVotes(): void
    {
        $html = $this->renderActions(Phase::Voting, new StoryQueue(items: [
            new StoryQueueItem('Story one'),
        ]), hasAnyVotes: true);

        self::assertDoesNotMatchRegularExpression('/pp-btn--outline[^>]*disabled/', $html);
    }

    private function renderActions(
        Phase $phase,
        StoryQueue $storyQueue,
        bool $canRevealQuorum = false,
        bool $hasAnyVotes = false,
    ): string {
        return $this->twig->render('@SymfinityPokerPlanner/room/_moderator_actions.html.twig', [
            'roomId' => 'room-1',
            'phase' => $phase,
            'storyQueue' => $storyQueue,
            'canRevealQuorum' => $canRevealQuorum,
            'hasAnyVotes' => $hasAnyVotes,
        ]);
    }
}
