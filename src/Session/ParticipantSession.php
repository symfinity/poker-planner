<?php

declare(strict_types=1);

namespace Symfinity\Bundle\PokerPlanner\Session;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

final class ParticipantSession
{
    private const PARTICIPANT_KEY = 'poker_planner.participant_id';
    private const ROOM_KEY = 'poker_planner.room_id';

    public function __construct(
        private readonly RequestStack $requestStack,
    ) {
    }

    public function getParticipantId(): ?string
    {
        $value = $this->session()?->get(self::PARTICIPANT_KEY);

        return is_string($value) ? $value : null;
    }

    public function getRoomId(): ?string
    {
        $value = $this->session()?->get(self::ROOM_KEY);

        return is_string($value) ? $value : null;
    }

    public function bind(string $roomId, string $participantId): void
    {
        $request = $this->requestStack->getCurrentRequest();
        if (null === $request) {
            return;
        }

        $session = $request->getSession();
        if (!$session->isStarted()) {
            $session->start();
        }

        $session->set(self::ROOM_KEY, $roomId);
        $session->set(self::PARTICIPANT_KEY, $participantId);
    }

    public function clear(): void
    {
        $session = $this->session();
        if (!$session instanceof SessionInterface) {
            return;
        }

        $session->remove(self::ROOM_KEY);
        $session->remove(self::PARTICIPANT_KEY);
    }

    private function session(): ?SessionInterface
    {
        $request = $this->requestStack->getCurrentRequest();
        if (null === $request) {
            return null;
        }

        $session = $request->getSession();

        if (!$session->isStarted() && !$request->hasPreviousSession()) {
            return null;
        }

        return $session;
    }
}
