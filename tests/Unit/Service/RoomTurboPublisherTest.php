<?php

declare(strict_types=1);

namespace Symfinity\Bundle\PokerPlanner\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use Symfinity\Bundle\PokerPlanner\Model\CardValue;
use Symfinity\Bundle\PokerPlanner\Model\Participant;
use Symfinity\Bundle\PokerPlanner\Model\Phase;
use Symfinity\Bundle\PokerPlanner\Model\Room;
use Symfinity\Bundle\PokerPlanner\Model\StoryQueue;
use Symfinity\Bundle\PokerPlanner\Service\ConsensusCalculator;
use Symfinity\Bundle\PokerPlanner\Service\DeckBuilder;
use Symfinity\Bundle\PokerPlanner\Service\RoomTurboPublisher;
use Symfinity\Bundle\PokerPlanner\Storage\InMemoryRoomStore;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Bridge\Twig\Extension\RoutingExtension;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Loader\FilesystemLoader;

final class RoomTurboPublisherTest extends TestCase
{
    public function testPublishGridDoesNotThrowWhenHubIsUnavailable(): void
    {
        $hub = $this->createMock(HubInterface::class);
        $hub->method('publish')->willThrowException(new \RuntimeException('Failed to send an update.'));

        $twig = $this->createMock(Environment::class);
        $twig->method('render')->willReturn('<turbo-stream action="update"></turbo-stream>');

        $deckBuilder = new DeckBuilder();
        $consensusCalculator = new ConsensusCalculator($deckBuilder);

        $publisher = new RoomTurboPublisher($hub, $twig, $deckBuilder, $consensusCalculator, '');
        $room = new Room(
            id: 'room-1',
            storyQueue: StoryQueue::fromLegacyTitle('Story'),
            phase: Phase::Voting,
            createdAt: time(),
            lastActivityAt: time(),
            participants: ['mod-1' => new Participant('mod-1', 'Alice', true)],
        );

        $publisher->publishGrid($room);

        self::assertTrue(true);
    }

    public function testVotingStreamDoesNotLeakVoteLabels(): void
    {
        $loader = new ArrayLoader([
            '@SymfinityPokerPlanner/room/_slot_grid.stream.html.twig' => '<turbo-stream>{% include "@grid" %}</turbo-stream>',
            '@grid' => '{% for participant in participants %}{{ participant.voteLabel }}{% endfor %}',
        ]);
        $twig = new Environment($loader);

        $deckBuilder = new DeckBuilder();
        $consensusCalculator = new ConsensusCalculator($deckBuilder);
        $publisher = new RoomTurboPublisher($this->createMock(HubInterface::class), $twig, $deckBuilder, $consensusCalculator, '');

        $room = new Room(
            id: 'room-1',
            storyQueue: new StoryQueue(),
            phase: Phase::Voting,
            createdAt: time(),
            lastActivityAt: time(),
            participants: [
                'dev' => new Participant(
                    id: 'dev',
                    displayName: 'Dev',
                    isModerator: false,
                    hasVoted: true,
                    voteValue: CardValue::Thirteen,
                ),
            ],
        );

        $html = $publisher->renderGrid($room);

        self::assertStringNotContainsString('13', $html);
    }

    public function testStoryStreamDoesNotLeakVotes(): void
    {
        $loader = new ArrayLoader([
            '@SymfinityPokerPlanner/room/_story_audience.stream.html.twig' => '{{ storyTitle }}',
        ]);
        $twig = new Environment($loader);
        $deckBuilder = new DeckBuilder();
        $consensusCalculator = new ConsensusCalculator($deckBuilder);
        $publisher = new RoomTurboPublisher($this->createMock(HubInterface::class), $twig, $deckBuilder, $consensusCalculator, '');

        $room = new Room(
            id: 'room-1',
            storyQueue: StoryQueue::fromLegacyTitle('Refinement item'),
            phase: Phase::Voting,
            createdAt: time(),
            lastActivityAt: time(),
            participants: [
                'dev' => new Participant(
                    id: 'dev',
                    displayName: 'Dev',
                    isModerator: false,
                    hasVoted: true,
                    voteValue: CardValue::Eight,
                ),
            ],
        );

        $html = $publisher->renderStory($room);

        self::assertStringContainsString('Refinement item', $html);
        self::assertStringNotContainsString('8', $html);
    }

    public function testRenderStoryRendersPackageTemplates(): void
    {
        $templateRoot = dirname(__DIR__, 3).'/templates';
        $loader = new FilesystemLoader();
        $loader->addPath($templateRoot, 'SymfinityPokerPlanner');
        $twig = new Environment($loader);
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('/r/demo/queue/remove');
        $twig->addExtension(new RoutingExtension($urlGenerator));

        $deckBuilder = new DeckBuilder();
        $consensusCalculator = new ConsensusCalculator($deckBuilder);
        $publisher = new RoomTurboPublisher($this->createMock(HubInterface::class), $twig, $deckBuilder, $consensusCalculator, '');

        $queue = new StoryQueue();
        $queue->addStory('Current story');
        $queue->addStory('Next story');

        $room = new Room(
            id: 'room-1',
            storyQueue: $queue,
            phase: Phase::Voting,
            createdAt: time(),
            lastActivityAt: time(),
        );

        $html = $publisher->renderStory($room);

        self::assertStringContainsString('turbo-stream', $html);
        self::assertStringContainsString('Current story', $html);
        self::assertStringContainsString('story-queue-count', $html);
        self::assertStringContainsString('pp-queue-list__remove-btn', $html);
        self::assertStringContainsString('action="update" target="story-queue-list"', $html);
    }

    public function testRenderStoryHidesQueueBadgeWhenEmpty(): void
    {
        $templateRoot = dirname(__DIR__, 3).'/templates';
        $loader = new FilesystemLoader();
        $loader->addPath($templateRoot, 'SymfinityPokerPlanner');
        $twig = new Environment($loader);
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('/r/demo/queue/remove');
        $twig->addExtension(new RoutingExtension($urlGenerator));

        $deckBuilder = new DeckBuilder();
        $consensusCalculator = new ConsensusCalculator($deckBuilder);
        $publisher = new RoomTurboPublisher($this->createMock(HubInterface::class), $twig, $deckBuilder, $consensusCalculator, '');

        $room = new Room(
            id: 'room-1',
            storyQueue: new StoryQueue(),
            phase: Phase::Voting,
            createdAt: time(),
            lastActivityAt: time(),
        );

        $html = $publisher->renderStory($room);

        self::assertStringContainsString('id="story-queue-count"', $html);
        self::assertStringContainsString('hidden', $html);
        self::assertStringNotContainsString('>0<', $html);
    }
}
