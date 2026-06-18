<?php

declare(strict_types=1);

namespace Symfinity\Bundle\PokerPlanner\Tests\Unit\Model;

use PHPUnit\Framework\TestCase;
use Symfinity\Bundle\PokerPlanner\Model\Phase;
use Symfinity\Bundle\PokerPlanner\Model\Room;

final class RoomTest extends TestCase
{
    public function testLegacyPayloadMigratesStoryTitleIntoQueue(): void
    {
        $room = Room::fromArray([
            'id' => 'room-legacy',
            'storyTitle' => 'Checkout',
            'phase' => Phase::Voting->value,
            'createdAt' => time(),
            'lastActivityAt' => time(),
            'participants' => [],
        ]);

        self::assertSame('Checkout', $room->getStoryTitle());
        self::assertSame(1, $room->storyQueue->count());
        self::assertSame(3, $room->toArray()['schemaVersion']);
    }
}
