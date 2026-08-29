<?php

declare(strict_types=1);

namespace Symfinity\Bundle\PokerPlanner\Tests\Unit\Session;

use PHPUnit\Framework\TestCase;
use Symfinity\Bundle\PokerPlanner\Session\ParticipantSession;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

final class ParticipantSessionTest extends TestCase
{
    public function testBindStartsSessionOnFirstVisit(): void
    {
        $session = new Session(new MockArraySessionStorage());
        $request = Request::create('/create', 'POST');
        $request->setSession($session);

        $requestStack = new RequestStack();
        $requestStack->push($request);

        $participantSession = new ParticipantSession($requestStack);
        $participantSession->bind('room-1', 'participant-1');

        self::assertTrue($session->isStarted());
        self::assertSame('room-1', $participantSession->getRoomId());
        self::assertSame('participant-1', $participantSession->getParticipantId());
    }
}
