<?php

declare(strict_types=1);

namespace Symfinity\Bundle\PokerPlanner\Tests\Unit\Model;

use PHPUnit\Framework\TestCase;
use Symfinity\Bundle\PokerPlanner\Model\StoryQueue;

final class StoryQueueTest extends TestCase
{
    public function testLegacyTitleMigration(): void
    {
        $queue = StoryQueue::fromLegacyTitle('Checkout flow');

        self::assertSame('Checkout flow', $queue->currentTitle());
        self::assertSame('Story 1 of 1', $queue->positionLabel());
    }

    public function testAddStoryAndAdvance(): void
    {
        $queue = new StoryQueue();
        $queue->addStory('Story A');
        $queue->addStory('Story B');

        self::assertSame(2, $queue->count());
        self::assertSame('Story A', $queue->currentTitle());

        $queue->recordCurrentEstimate('5');
        $queue->advance();

        self::assertSame('Story B', $queue->currentTitle());
        self::assertSame('5', $queue->items[0]->recordedEstimate);
    }

    public function testRecapRowsSkipUnrecorded(): void
    {
        $queue = new StoryQueue();
        $queue->addStory('One');
        $queue->addStory('Two');
        $queue->recordCurrentEstimate('3');

        self::assertCount(1, $queue->recapRows());
    }

    public function testFinishQueueMarksComplete(): void
    {
        $queue = new StoryQueue();
        $queue->addStory('Only story');
        $queue->recordCurrentEstimate('8');
        $queue->markComplete();

        self::assertTrue($queue->complete);
        self::assertSame('Queue complete', $queue->positionLabel());
    }

    public function testArchiveAndStartNewSessionPreservesRecap(): void
    {
        $queue = new StoryQueue();
        $queue->addStory('Story one');
        $queue->addStory('Story two');
        $queue->recordCurrentEstimate('3');
        $queue->advance();
        $queue->recordCurrentEstimate('5');
        $queue->markComplete();

        self::assertCount(2, $queue->recapRows());

        $queue->archiveAndStartNewSession();

        self::assertFalse($queue->complete);
        self::assertSame(0, $queue->count());
        self::assertCount(2, $queue->recapRows());

        $queue->addStory('Story three');
        $queue->recordCurrentEstimate('8');

        self::assertCount(3, $queue->recapRows());
    }

    public function testArchiveRequiresCompletedQueue(): void
    {
        $queue = new StoryQueue();
        $queue->addStory('Open story');

        $this->expectException(\DomainException::class);
        $queue->archiveAndStartNewSession();
    }

    public function testRemoveFutureStory(): void
    {
        $queue = new StoryQueue();
        $queue->addStory('Current');
        $queue->addStory('Future');
        $queue->removeStory(1);

        self::assertSame(1, $queue->count());
        self::assertSame('Current', $queue->currentTitle());
    }
}
