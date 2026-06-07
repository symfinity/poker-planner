<?php

declare(strict_types=1);

namespace Symfinity\Bundle\PokerPlanner\Controller;

use Symfinity\Bundle\PokerPlanner\Model\CardValue;
use Symfinity\Bundle\PokerPlanner\Model\Phase;
use Symfinity\Bundle\PokerPlanner\Model\PublicParticipantView;
use Symfinity\Bundle\PokerPlanner\Model\Room;
use Symfinity\Bundle\PokerPlanner\Service\RoomService;
use Symfinity\Bundle\PokerPlanner\Service\RoomTurboPublisher;
use Symfinity\Bundle\PokerPlanner\Session\ParticipantSession;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\UX\Turbo\TurboBundle;

#[Route('r/{uuid}', name: 'poker_planner_')]
final class RoomController extends AbstractController
{
    public function __construct(
        private readonly RoomService $rooms,
        private readonly RoomTurboPublisher $publisher,
        private readonly ParticipantSession $participantSession,
        private readonly int $heartbeatSeconds,
    ) {
    }

    #[Route('', name: 'room', methods: ['GET'])]
    public function show(string $uuid): Response
    {
        $room = $this->rooms->getRoom($uuid);
        if (!$room instanceof Room) {
            throw $this->createNotFoundException('Room not found.');
        }

        $participantId = $this->participantSession->getParticipantId();
        if (null === $participantId || null === $room->findParticipant($participantId)) {
            return $this->redirectToRoute('poker_planner_entry', ['room' => $uuid]);
        }

        return $this->render('@SymfinityPokerPlanner/room/show.html.twig', $this->viewModel($room, $participantId));
    }

    #[Route('/vote', name: 'vote', methods: ['POST'])]
    public function vote(string $uuid, Request $request): Response
    {
        $participantId = $this->requireParticipantId($uuid);
        $cardRaw = (string) $request->request->get('card', '');

        try {
            $card = CardValue::from($cardRaw);
            $room = $this->rooms->vote($uuid, $participantId, $card);
        } catch (\DomainException|\ValueError $exception) {
            if ($request->headers->get('Turbo-Frame')) {
                return new Response($exception->getMessage(), Response::HTTP_BAD_REQUEST);
            }

            $this->addFlash('error', $exception->getMessage());

            return $this->redirectToRoute('poker_planner_room', ['uuid' => $uuid]);
        }

        $this->publisher->publishGrid($room);

        $request->setRequestFormat(TurboBundle::STREAM_FORMAT);

        return $this->render('@SymfinityPokerPlanner/room/_slot_grid.stream.html.twig', [
            'participants' => $this->publicParticipants($room),
            'phase' => $room->phase,
        ]);
    }

    #[Route('/vote/clear', name: 'vote_clear', methods: ['POST'])]
    public function clearVote(string $uuid, Request $request): Response
    {
        $participantId = $this->requireParticipantId($uuid);

        try {
            $room = $this->rooms->clearVote($uuid, $participantId);
        } catch (\DomainException $exception) {
            if ($request->headers->get('Turbo-Frame')) {
                return new Response($exception->getMessage(), Response::HTTP_BAD_REQUEST);
            }

            $this->addFlash('error', $exception->getMessage());

            return $this->redirectToRoute('poker_planner_room', ['uuid' => $uuid]);
        }

        $this->publisher->publishGrid($room);

        $request->setRequestFormat(TurboBundle::STREAM_FORMAT);

        return $this->render('@SymfinityPokerPlanner/room/_slot_grid.stream.html.twig', [
            'participants' => $this->publicParticipants($room),
            'phase' => $room->phase,
        ]);
    }

    #[Route('/reveal', name: 'reveal', methods: ['POST'])]
    public function reveal(string $uuid, Request $request): Response
    {
        $participantId = $this->requireParticipantId($uuid);

        try {
            $room = $this->rooms->reveal($uuid, $participantId);
        } catch (\DomainException $exception) {
            $this->addFlash('error', $exception->getMessage());

            return $this->redirectToRoute('poker_planner_room', ['uuid' => $uuid]);
        }

        $this->publisher->publishSessionSync($room, true);

        $request->setRequestFormat(TurboBundle::STREAM_FORMAT);

        return $this->render('@SymfinityPokerPlanner/room/_phase_change.stream.html.twig', [
            'participants' => $this->publicParticipants($room, true),
            'phase' => Phase::Revealed,
            'roomId' => $room->id,
            'deck' => CardValue::deck(),
            'includeModeratorActions' => true,
        ]);
    }

    #[Route('/restart', name: 'restart', methods: ['POST'])]
    public function restart(string $uuid, Request $request): Response
    {
        $participantId = $this->requireParticipantId($uuid);

        try {
            $room = $this->rooms->restart($uuid, $participantId);
        } catch (\DomainException $exception) {
            $this->addFlash('error', $exception->getMessage());

            return $this->redirectToRoute('poker_planner_room', ['uuid' => $uuid]);
        }

        $this->publisher->publishSessionSync($room);

        $request->setRequestFormat(TurboBundle::STREAM_FORMAT);

        return $this->render('@SymfinityPokerPlanner/room/_phase_change.stream.html.twig', [
            'participants' => $this->publicParticipants($room),
            'phase' => Phase::Voting,
            'roomId' => $room->id,
            'deck' => CardValue::deck(),
            'includeModeratorActions' => true,
        ]);
    }

    #[Route('/story', name: 'story', methods: ['POST'])]
    public function story(string $uuid, Request $request): Response
    {
        $participantId = $this->requireParticipantId($uuid);
        $title = trim((string) $request->request->get('story_title', ''));

        try {
            $room = $this->rooms->setStoryTitle($uuid, $participantId, $title);
        } catch (\DomainException $exception) {
            $this->addFlash('error', $exception->getMessage());

            return $this->redirectToRoute('poker_planner_room', ['uuid' => $uuid]);
        }

        $this->publisher->publishStory($room);

        $request->setRequestFormat(TurboBundle::STREAM_FORMAT);

        return $this->render('@SymfinityPokerPlanner/room/_story.stream.html.twig', [
            'storyTitle' => $room->storyTitle,
        ]);
    }

    #[Route('/heartbeat', name: 'heartbeat', methods: ['POST'])]
    public function heartbeat(string $uuid): Response
    {
        $participantId = $this->requireParticipantId($uuid);

        try {
            $this->rooms->heartbeat($uuid, $participantId);
        } catch (\DomainException) {
            return new Response('', Response::HTTP_GONE);
        }

        return new Response('', Response::HTTP_NO_CONTENT);
    }

    private function requireParticipantId(string $uuid): string
    {
        $participantId = $this->participantSession->getParticipantId();
        if (null === $participantId || $this->participantSession->getRoomId() !== $uuid) {
            throw $this->createAccessDeniedException('Join the room first.');
        }

        return $participantId;
    }

    /**
     * @return array<string, mixed>
     */
    private function viewModel(Room $room, string $participantId): array
    {
        $self = $room->findParticipant($participantId);

        return [
            'room' => $room,
            'participants' => $this->publicParticipants($room),
            'phase' => $room->phase,
            'self' => $self,
            'deck' => CardValue::deck(),
            'mercure_topic' => $this->publisher->topic($room->id),
            'heartbeat_seconds' => $this->heartbeatSeconds,
        ];
    }

    /**
     * @return list<PublicParticipantView>
     */
    private function publicParticipants(Room $room, bool $forceReveal = false): array
    {
        $phase = $forceReveal ? Phase::Revealed : $room->phase;
        $views = [];
        foreach ($room->participants as $participant) {
            $views[] = PublicParticipantView::fromParticipant($participant, $phase);
        }

        return $views;
    }
}
