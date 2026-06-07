<?php

declare(strict_types=1);

namespace Symfinity\Bundle\PokerPlanner\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use Symfinity\Bundle\PokerPlanner\Model\Participant;
use Symfinity\Bundle\PokerPlanner\Model\Phase;
use Symfinity\Bundle\PokerPlanner\Model\Room;
use Symfinity\Bundle\PokerPlanner\Service\RoomTurboPublisher;
use Symfony\Component\Mercure\Exception\RuntimeException as MercureRuntimeException;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Twig\Environment;

final class RoomTurboPublisherTest extends TestCase
{
    public function testPublishGridDoesNotThrowWhenHubIsUnavailable(): void
    {
        $hub = $this->createMock(HubInterface::class);
        $hub->expects(self::once())
            ->method('publish')
            ->with(self::isInstanceOf(Update::class))
            ->willThrowException(new MercureRuntimeException('Failed to send an update.'));

        $twig = $this->createMock(Environment::class);
        $twig->method('render')->willReturn('<turbo-stream action="update"></turbo-stream>');

        $publisher = new RoomTurboPublisher($hub, $twig, '');
        $room = new Room(
            id: 'room-1',
            storyTitle: 'Story',
            phase: Phase::Voting,
            createdAt: time(),
            lastActivityAt: time(),
            participants: ['mod-1' => new Participant('mod-1', 'Alice', true)],
        );

        $publisher->publishGrid($room);

        self::assertTrue(true);
    }
}
