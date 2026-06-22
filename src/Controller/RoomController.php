<?php

declare(strict_types=1);

namespace Symfinity\Bundle\PokerPlanner\Controller;

use Symfinity\Bundle\PokerPlanner\Model\CardValue;
use Symfinity\Bundle\PokerPlanner\Model\ConsensusSummary;
use Symfinity\Bundle\PokerPlanner\Model\DeckPreset;
use Symfinity\Bundle\PokerPlanner\Model\Phase;
use Symfinity\Bundle\PokerPlanner\Model\PublicParticipantView;
use Symfinity\Bundle\PokerPlanner\Model\Room;
use Symfinity\Bundle\PokerPlanner\Model\RoomSettings;
use Symfinity\Bundle\PokerPlanner\Model\RoundingMode;
use Symfinity\Bundle\PokerPlanner\Service\DeckBuilder;
use Symfinity\Bundle\PokerPlanner\Service\RoomService;
use Symfinity\Bundle\PokerPlanner\Service\RoomTurboPublisher;
use Symfinity\Bundle\PokerPlanner\Session\ParticipantSession;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\UX\Turbo\TurboBundle;

#[Route('r/{uuid}', name: 'poker_planner_')]
final class RoomController extends AbstractController
{
    public function __construct(
        private readonly RoomService $rooms,
        private readonly RoomTurboPublisher $publisher,
        private readonly DeckBuilder $deckBuilder,
        private readonly ParticipantSession $participantSession,
        private readonly int $heartbeatSeconds,
    ) {
    }

    #[Route('', name: 'room', methods: ['GET'])]
    public function show(string $uuid): Response
    {
        try {
            $room = $this->rooms->getRoom($uuid);
        } catch (\DomainException) {
            return $this->redirectRoomGone();
        }

        if (!$room instanceof Room) {
            return $this->redirectRoomGone();
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
            $roomBefore = $this->rooms->getRoom($uuid);
            $wasVoted = $roomBefore?->findParticipant($participantId)?->hasVoted ?? false;
            $room = $this->rooms->vote($uuid, $participantId, $card);
        } catch (\DomainException|\ValueError|\InvalidArgumentException $exception) {
            if ($response = $this->redirectIfRoomGone($exception)) {
                return $response;
            }

            if ($request->headers->get('Turbo-Frame')) {
                return new Response($exception->getMessage(), Response::HTTP_BAD_REQUEST);
            }

            $this->addFlash('error', $exception->getMessage());

            return $this->redirectToRoute('poker_planner_room', ['uuid' => $uuid]);
        }

        $refreshSlotGrid = !$wasVoted
            || (Phase::Revealed === $room->phase && $room->settings->allowChangeAfterReveal);

        $consensus = Phase::Revealed === $room->phase ? $this->rooms->consensus($room) : null;
        $roundedMedianLabel = null !== $consensus && $consensus->hasNumericConsensus()
            ? $this->rooms->roundedMedianLabel($room)
            : null;

        if ($refreshSlotGrid) {
            if (null !== $consensus) {
                $this->publisher->publishSessionSync($room, true, $consensus);
            } else {
                $this->publisher->publishGrid($room);
            }
        }

        $self = $room->findParticipant($participantId);
        $selectedVote = $self?->voteValue?->value;

        $request->setRequestFormat(TurboBundle::STREAM_FORMAT);

        return $this->render('@SymfinityPokerPlanner/room/_vote_update.stream.html.twig', array_merge([
            'participants' => $this->publicParticipants($room),
            'phase' => $room->phase,
            'roomId' => $room->id,
            'deck' => $this->deckBuilder->buildForRoom($room),
            'room' => $room,
            'selectedVote' => $selectedVote,
            'consensus' => $consensus,
            'roundedMedianLabel' => $roundedMedianLabel,
            'refreshSlotGrid' => $refreshSlotGrid,
            'refreshModeratorActions' => Phase::Voting === $room->phase,
        ], $this->moderatorActionContext($room)));
    }

    #[Route('/vote/clear', name: 'vote_clear', methods: ['POST'])]
    public function clearVote(string $uuid, Request $request): Response
    {
        $participantId = $this->requireParticipantId($uuid);

        try {
            $room = $this->rooms->clearVote($uuid, $participantId);
        } catch (\DomainException $exception) {
            if ($response = $this->redirectIfRoomGone($exception)) {
                return $response;
            }

            if ($request->headers->get('Turbo-Frame')) {
                return new Response($exception->getMessage(), Response::HTTP_BAD_REQUEST);
            }

            $this->addFlash('error', $exception->getMessage());

            return $this->redirectToRoute('poker_planner_room', ['uuid' => $uuid]);
        }

        $consensus = Phase::Revealed === $room->phase ? $this->rooms->consensus($room) : null;
        $roundedMedianLabel = null !== $consensus && $consensus->hasNumericConsensus()
            ? $this->rooms->roundedMedianLabel($room)
            : null;

        if (null !== $consensus) {
            $this->publisher->publishSessionSync($room, true, $consensus);
        } else {
            $this->publisher->publishGrid($room);
        }

        $request->setRequestFormat(TurboBundle::STREAM_FORMAT);

        return $this->render('@SymfinityPokerPlanner/room/_vote_update.stream.html.twig', array_merge([
            'participants' => $this->publicParticipants($room),
            'phase' => $room->phase,
            'roomId' => $room->id,
            'deck' => $this->deckBuilder->buildForRoom($room),
            'room' => $room,
            'selectedVote' => null,
            'consensus' => $consensus,
            'roundedMedianLabel' => $roundedMedianLabel,
            'refreshSlotGrid' => true,
            'refreshModeratorActions' => Phase::Voting === $room->phase,
        ], $this->moderatorActionContext($room)));
    }

    #[Route('/reveal', name: 'reveal', methods: ['POST'])]
    public function reveal(string $uuid, Request $request): Response
    {
        $participantId = $this->requireParticipantId($uuid);

        try {
            $room = $this->rooms->reveal($uuid, $participantId);
        } catch (\DomainException $exception) {
            if ($response = $this->redirectIfRoomGone($exception)) {
                return $response;
            }

            $this->addFlash('error', $exception->getMessage());

            return $this->redirectToRoute('poker_planner_room', ['uuid' => $uuid]);
        }

        $consensus = $this->rooms->consensus($room);
        $this->publisher->publishSessionSync($room, true, $consensus, false, true);

        $request->setRequestFormat(TurboBundle::STREAM_FORMAT);

        return $this->render('@SymfinityPokerPlanner/room/_phase_change.stream.html.twig', array_merge([
            'participants' => $this->publicParticipants($room, true),
            'phase' => Phase::Revealed,
            'roomId' => $room->id,
            'deck' => $this->deckBuilder->buildForRoom($room),
            'room' => $room,
            'includeModeratorActions' => true,
            'consensus' => $consensus,
            'roundedMedianLabel' => $consensus->hasNumericConsensus()
                ? $this->rooms->roundedMedianLabel($room)
                : null,
            'storyQueue' => $room->storyQueue,
            'storyTitle' => $room->getStoryTitle(),
            'recapRows' => $room->storyQueue->recapRows(),
            'animateReveal' => true,
        ], $this->moderatorActionContext($room)));
    }

    #[Route('/restart', name: 'restart', methods: ['POST'])]
    public function restart(string $uuid, Request $request): Response
    {
        $participantId = $this->requireParticipantId($uuid);

        try {
            $room = $this->rooms->restart($uuid, $participantId);
        } catch (\DomainException $exception) {
            if ($response = $this->redirectIfRoomGone($exception)) {
                return $response;
            }

            $this->addFlash('error', $exception->getMessage());

            return $this->redirectToRoute('poker_planner_room', ['uuid' => $uuid]);
        }

        $this->publisher->publishSessionSync($room);

        $request->setRequestFormat(TurboBundle::STREAM_FORMAT);

        return $this->render('@SymfinityPokerPlanner/room/_phase_change.stream.html.twig', array_merge([
            'participants' => $this->publicParticipants($room),
            'phase' => $room->phase,
            'roomId' => $room->id,
            'deck' => $this->deckBuilder->buildForRoom($room),
            'room' => $room,
            'includeModeratorActions' => true,
            'consensus' => null,
            'storyQueue' => $room->storyQueue,
            'storyTitle' => $room->getStoryTitle(),
            'recapRows' => $room->storyQueue->recapRows(),
        ], $this->moderatorActionContext($room)));
    }

    #[Route('/session/new', name: 'session_new', methods: ['POST'])]
    public function startNewSession(string $uuid, Request $request): Response
    {
        $participantId = $this->requireParticipantId($uuid);

        try {
            $room = $this->rooms->startNewSession($uuid, $participantId);
        } catch (\DomainException $exception) {
            if ($response = $this->redirectIfRoomGone($exception)) {
                return $response;
            }

            $this->addFlash('error', $exception->getMessage());

            return $this->redirectToRoute('poker_planner_room', ['uuid' => $uuid]);
        }

        $self = $room->findParticipant($participantId);
        $this->publisher->publishSessionSync($room, false, null);

        $request->setRequestFormat(TurboBundle::STREAM_FORMAT);

        return $this->render('@SymfinityPokerPlanner/room/_phase_change.stream.html.twig', array_merge([
            'participants' => $this->publicParticipants($room),
            'phase' => $room->phase,
            'roomId' => $room->id,
            'deck' => $this->deckBuilder->buildForRoom($room),
            'room' => $room,
            'includeModeratorActions' => true,
            'consensus' => null,
            'storyQueue' => $room->storyQueue,
            'storyTitle' => $room->getStoryTitle(),
            'recapRows' => $room->storyQueue->recapRows(),
            'refreshQueueDrawer' => true,
            'isModerator' => null !== $self && $self->isModerator,
        ], $this->moderatorActionContext($room)));
    }

    #[Route('/story', name: 'story', methods: ['POST'])]
    public function story(string $uuid, Request $request): Response
    {
        $participantId = $this->requireParticipantId($uuid);
        $title = trim((string) $request->request->get('story_title', ''));

        try {
            $room = $this->rooms->setStoryTitle($uuid, $participantId, $title);
        } catch (\DomainException $exception) {
            if ($response = $this->redirectIfRoomGone($exception)) {
                return $response;
            }

            $this->addFlash('error', $exception->getMessage());

            return $this->redirectToRoute('poker_planner_room', ['uuid' => $uuid]);
        }

        $this->publisher->publishStory($room);

        $request->setRequestFormat(TurboBundle::STREAM_FORMAT);

        return $this->renderStoryStreams($room, $participantId);
    }

    #[Route('/queue/add', name: 'queue_add', methods: ['POST'])]
    public function addStory(string $uuid, Request $request): Response
    {
        $participantId = $this->requireParticipantId($uuid);
        $title = trim((string) $request->request->get('story_title', ''));

        try {
            $room = $this->rooms->addStory($uuid, $participantId, $title);
        } catch (\DomainException|\InvalidArgumentException $exception) {
            if ($response = $this->redirectIfRoomGone($exception)) {
                return $response;
            }

            $this->addFlash('error', $exception->getMessage());

            return $this->redirectToRoute('poker_planner_room', ['uuid' => $uuid]);
        }

        $this->publisher->publishStory($room);

        $request->setRequestFormat(TurboBundle::STREAM_FORMAT);

        return $this->renderStoryStreams($room, $participantId);
    }

    #[Route('/queue/remove', name: 'queue_remove', methods: ['POST'])]
    public function removeStory(string $uuid, Request $request): Response
    {
        $participantId = $this->requireParticipantId($uuid);
        $index = (int) $request->request->get('story_index', -1);

        try {
            $room = $this->rooms->removeStory($uuid, $participantId, $index);
        } catch (\DomainException|\InvalidArgumentException $exception) {
            if ($response = $this->redirectIfRoomGone($exception)) {
                return $response;
            }

            $this->addFlash('error', $exception->getMessage());

            return $this->redirectToRoute('poker_planner_room', ['uuid' => $uuid]);
        }

        $this->publisher->publishStory($room);

        $request->setRequestFormat(TurboBundle::STREAM_FORMAT);

        return $this->renderStoryStreams($room, $participantId);
    }

    private function renderStoryStreams(Room $room, string $participantId): Response
    {
        $self = $room->findParticipant($participantId);

        return $this->render('@SymfinityPokerPlanner/room/_story.stream.html.twig', array_merge([
            'storyTitle' => $room->getStoryTitle(),
            'storyQueue' => $room->storyQueue,
            'roomId' => $room->id,
            'room' => $room,
            'phase' => $room->phase,
            'isModerator' => null !== $self && $self->isModerator,
        ], $this->moderatorActionContext($room)));
    }

    #[Route('/rename', name: 'rename', methods: ['POST'])]
    public function rename(string $uuid, Request $request): Response
    {
        $participantId = $this->requireParticipantId($uuid);
        $name = trim((string) $request->request->get('display_name', ''));

        try {
            $room = $this->rooms->renameParticipant($uuid, $participantId, $name);
        } catch (\DomainException|\InvalidArgumentException $exception) {
            if ($response = $this->redirectIfRoomGone($exception)) {
                return $response;
            }

            if (str_contains((string) $request->headers->get('Accept'), 'turbo-stream')) {
                return new Response($exception->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $this->addFlash('error', $exception->getMessage());

            return $this->redirectToRoute('poker_planner_room', ['uuid' => $uuid]);
        }

        $this->publisher->publishGrid($room);

        if (str_contains((string) $request->headers->get('Accept'), 'turbo-stream')) {
            return new Response('', Response::HTTP_NO_CONTENT);
        }

        return $this->redirectToRoute('poker_planner_room', ['uuid' => $uuid]);
    }

    #[Route('/next', name: 'next_story', methods: ['POST'])]
    public function nextStory(string $uuid, Request $request): Response
    {
        $participantId = $this->requireParticipantId($uuid);
        $estimate = trim((string) $request->request->get('recorded_estimate', ''));
        $recordedEstimate = '' !== $estimate ? $estimate : null;

        try {
            $room = $this->rooms->nextStory($uuid, $participantId, $recordedEstimate);
        } catch (\DomainException $exception) {
            if ($response = $this->redirectIfRoomGone($exception)) {
                return $response;
            }

            $this->addFlash('error', $exception->getMessage());

            return $this->redirectToRoute('poker_planner_room', ['uuid' => $uuid]);
        }

        $consensus = Phase::Revealed === $room->phase ? $this->rooms->consensus($room) : null;
        $finishedQueue = 0 === $room->storyQueue->count() && Phase::Revealed === $room->phase;
        $this->publisher->publishSessionSync($room, Phase::Revealed === $room->phase, $consensus);

        $self = $room->findParticipant($participantId);
        $request->setRequestFormat(TurboBundle::STREAM_FORMAT);

        return $this->render('@SymfinityPokerPlanner/room/_phase_change.stream.html.twig', array_merge([
            'participants' => $this->publicParticipants($room),
            'phase' => $room->phase,
            'roomId' => $room->id,
            'deck' => $this->deckBuilder->buildForRoom($room),
            'room' => $room,
            'includeModeratorActions' => true,
            'consensus' => $consensus,
            'storyQueue' => $room->storyQueue,
            'storyTitle' => $room->getStoryTitle(),
            'recapRows' => $room->storyQueue->recapRows(),
            'roundedMedianLabel' => null !== $consensus && $consensus->hasNumericConsensus()
                ? $this->rooms->roundedMedianLabel($room)
                : null,
            'refreshQueueDrawer' => $finishedQueue || Phase::Voting === $room->phase,
            'isModerator' => null !== $self && $self->isModerator,
        ], $this->moderatorActionContext($room)));
    }

    #[Route('/settings', name: 'settings', methods: ['POST'])]
    public function updateSettings(string $uuid, Request $request): Response
    {
        $participantId = $this->requireParticipantId($uuid);

        try {
            $room = $this->rooms->getRoom($uuid);
        } catch (\DomainException) {
            return $this->redirectRoomGone();
        }

        if (!$room instanceof Room) {
            return $this->redirectRoomGone();
        }

        $preset = DeckPreset::tryFrom((string) $request->request->get('deck_preset', '')) ?? $room->settings->deckPreset;
        $rounding = RoundingMode::tryFrom((string) $request->request->get('rounding_mode', '')) ?? $room->settings->roundingMode;

        $settings = new RoomSettings(
            teamName: $room->settings->teamName,
            saved: $room->settings->saved,
            deckPreset: $preset,
            optionalZero: $request->request->getBoolean('optional_zero'),
            optionalPass: $request->request->getBoolean('optional_pass'),
            optionalBreak: $request->request->getBoolean('optional_break'),
            roundingMode: $rounding,
            allowChangeAfterReveal: $request->request->getBoolean('allow_change_after_reveal'),
            showConfetti: $request->request->getBoolean('show_confetti'),
        );

        try {
            $room = $this->rooms->updateRoomSettings($uuid, $participantId, $settings);
        } catch (\DomainException|\InvalidArgumentException $exception) {
            if ($response = $this->redirectIfRoomGone($exception)) {
                return $response;
            }

            $this->addFlash('error', $exception->getMessage());

            return $this->redirectToRoute('poker_planner_room', ['uuid' => $uuid]);
        }

        $this->publisher->publishSessionSync($room);

        return $this->redirectToRoute('poker_planner_room', ['uuid' => $uuid]);
    }

    #[Route('/save', name: 'save_room', methods: ['POST'])]
    public function saveRoom(string $uuid, Request $request): Response
    {
        $participantId = $this->requireParticipantId($uuid);
        $teamName = trim((string) $request->request->get('team_name', ''));

        try {
            $this->rooms->saveRoom($uuid, $participantId, $teamName);
        } catch (\DomainException|\InvalidArgumentException $exception) {
            if ($response = $this->redirectIfRoomGone($exception)) {
                return $response;
            }

            $this->addFlash('error', $exception->getMessage());

            return $this->redirectToRoute('poker_planner_room', ['uuid' => $uuid]);
        }

        return $this->redirectToRoute('poker_planner_room', ['uuid' => $uuid]);
    }

    #[Route('/delete', name: 'delete_room', methods: ['POST'])]
    public function deleteRoom(string $uuid): Response
    {
        $participantId = $this->requireParticipantId($uuid);

        try {
            $this->rooms->deleteRoom($uuid, $participantId);
        } catch (\DomainException $exception) {
            if ($response = $this->redirectIfRoomGone($exception)) {
                return $response;
            }

            $this->addFlash('error', $exception->getMessage());

            return $this->redirectToRoute('poker_planner_room', ['uuid' => $uuid]);
        }

        $this->participantSession->clear();

        return $this->redirectToRoute('poker_planner_entry');
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
            throw new AccessDeniedHttpException('Join the room first.');
        }

        return $participantId;
    }

    /**
     * @return array<string, mixed>
     */
    private function viewModel(Room $room, string $participantId): array
    {
        $self = $room->findParticipant($participantId);
        $consensus = Phase::Revealed === $room->phase ? $this->rooms->consensus($room) : null;

        return [
            'room' => $room,
            'participants' => $this->publicParticipants($room),
            'phase' => $room->phase,
            'self' => $self,
            'deck' => $this->deckBuilder->buildForRoom($room),
            'mercure_topic' => $this->publisher->topic($room->id),
            'heartbeat_seconds' => $this->heartbeatSeconds,
            'storyQueue' => $room->storyQueue,
            'consensus' => $consensus,
            'roundedMedianLabel' => null !== $consensus && $consensus->hasNumericConsensus()
                ? $this->rooms->roundedMedianLabel($room)
                : null,
            'recapRows' => $room->storyQueue->recapRows(),
            'deckPresets' => DeckPreset::cases(),
            'roundingModes' => RoundingMode::cases(),
            'isModerator' => null !== $self && $self->isModerator,
            'canRevealQuorum' => $room->hasRevealQuorum(),
            'hasAnyVotes' => $room->hasAnyVotes(),
        ];
    }

    /**
     * @return array{roomId: string, phase: Phase, storyQueue: \Symfinity\Bundle\PokerPlanner\Model\StoryQueue, canRevealQuorum: bool, hasAnyVotes: bool}
     */
    private function moderatorActionContext(Room $room): array
    {
        return [
            'roomId' => $room->id,
            'phase' => $room->phase,
            'storyQueue' => $room->storyQueue,
            'canRevealQuorum' => $room->hasRevealQuorum(),
            'hasAnyVotes' => $room->hasAnyVotes(),
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

    private function redirectRoomGone(): Response
    {
        $this->participantSession->clear();
        $this->addFlash('warning', 'This room is no longer available. Start a new session or ask for an updated link.');

        return $this->redirectToRoute('poker_planner_entry');
    }

    private function redirectIfRoomGone(\Throwable $exception): ?Response
    {
        if (!$exception instanceof \DomainException || !$this->isRoomUnavailable($exception)) {
            return null;
        }

        return $this->redirectRoomGone();
    }

    private function isRoomUnavailable(\DomainException $exception): bool
    {
        return \in_array($exception->getMessage(), [
            'Room not found.',
            'Room expired.',
            'Room closed.',
        ], true);
    }
}
