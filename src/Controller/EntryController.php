<?php

declare(strict_types=1);

namespace Symfinity\Bundle\PokerPlanner\Controller;

use Symfinity\Bundle\PokerPlanner\Service\RoomService;
use Symfinity\Bundle\PokerPlanner\Service\RoomTurboPublisher;
use Symfinity\Bundle\PokerPlanner\Session\ParticipantSession;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(name: 'poker_planner_')]
final class EntryController extends AbstractController
{
    public function __construct(
        private readonly RoomService $rooms,
        private readonly RoomTurboPublisher $publisher,
        private readonly ParticipantSession $participantSession,
    ) {
    }

    #[Route('', name: 'entry', methods: ['GET'])]
    public function entry(Request $request): Response
    {
        return $this->render('@SymfinityPokerPlanner/entry/index.html.twig', [
            'roomId' => $this->normalizeRoomId((string) $request->query->get('room', '')),
        ]);
    }

    #[Route('create', name: 'create', methods: ['POST'])]
    public function create(Request $request): Response
    {
        $name = trim((string) $request->request->get('display_name', ''));
        if ('' === $name) {
            $this->addFlash('error', 'Display name is required.');

            return $this->redirectToRoute('poker_planner_entry');
        }

        $room = $this->rooms->createRoom($name);
        $moderator = $room->moderator();
        if (null === $moderator) {
            throw new \RuntimeException('Moderator missing after room create.');
        }

        $this->participantSession->bind($room->id, $moderator->id);

        return $this->redirectToRoute('poker_planner_room', ['uuid' => $room->id]);
    }

    #[Route('join', name: 'join', methods: ['POST'])]
    public function join(Request $request): Response
    {
        $name = trim((string) $request->request->get('display_name', ''));
        $roomId = $this->normalizeRoomId((string) $request->request->get('room_id', ''));

        if ('' === $name || '' === $roomId) {
            $this->addFlash('error', 'Display name and room link are required.');

            return $this->redirectToRoute('poker_planner_entry', $this->entryQueryForRoom($roomId));
        }

        try {
            $join = $this->rooms->joinRoom($roomId, $name);
        } catch (\DomainException $exception) {
            if (\in_array($exception->getMessage(), ['Room not found.', 'Room expired.', 'Room closed.'], true)) {
                $this->addFlash('warning', 'This room is no longer available. Start a new session or ask for an updated link.');

                return $this->redirectToRoute('poker_planner_entry');
            }

            $this->addFlash('error', $exception->getMessage());

            return $this->redirectToRoute('poker_planner_entry', $this->entryQueryForRoom($roomId));
        }

        $room = $join['room'];
        $this->participantSession->bind($room->id, $join['participantId']);
        $this->publisher->publishGrid($room);

        return $this->redirectToRoute('poker_planner_room', ['uuid' => $room->id]);
    }

    /**
     * @return array{room?: string}
     */
    private function entryQueryForRoom(string $roomId): array
    {
        return '' !== $roomId ? ['room' => $roomId] : [];
    }

    private function normalizeRoomId(string $raw): string
    {
        $raw = trim($raw);
        if ('' === $raw) {
            return '';
        }

        if (preg_match(
            '#/r/([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})#i',
            $raw,
            $matches,
        )) {
            return strtolower($matches[1]);
        }

        if (preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $raw,
        )) {
            return strtolower($raw);
        }

        return $raw;
    }
}
