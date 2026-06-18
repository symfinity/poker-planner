<?php

declare(strict_types=1);

namespace Symfinity\Bundle\PokerPlanner\Service;

use Symfinity\Bundle\PokerPlanner\Model\ConsensusSummary;
use Symfinity\Bundle\PokerPlanner\Model\Phase;
use Symfinity\Bundle\PokerPlanner\Model\PublicParticipantView;
use Symfinity\Bundle\PokerPlanner\Model\Room;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Psr\Log\LoggerInterface;
use Twig\Environment;

final class RoomTurboPublisher
{
    public function __construct(
        private readonly HubInterface $hub,
        private readonly Environment $twig,
        private readonly DeckBuilder $deckBuilder,
        private readonly ConsensusCalculator $consensusCalculator,
        private readonly string $topicPrefix,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function publishGrid(Room $room, bool $revealed = false): void
    {
        $this->publish($room->id, $this->renderGrid($room, $revealed));
    }

    public function publishSessionSync(Room $room, bool $revealed = false, ?ConsensusSummary $consensus = null, bool $refreshQueueDrawer = false): void
    {
        $this->publish($room->id, $this->renderSessionSync($room, $revealed, $consensus, $refreshQueueDrawer));
    }

    public function renderGrid(Room $room, bool $revealed = false): string
    {
        $phase = $this->resolvePhase($room, $revealed);

        return $this->twig->render('@SymfinityPokerPlanner/room/_slot_grid.stream.html.twig', [
            'participants' => $this->publicParticipants($room, $phase),
            'phase' => $phase,
        ]);
    }

    public function renderSessionSync(Room $room, bool $revealed = false, ?ConsensusSummary $consensus = null, bool $refreshQueueDrawer = false): string
    {
        $phase = $this->resolvePhase($room, $revealed);

        return $this->twig->render('@SymfinityPokerPlanner/room/_session_sync.stream.html.twig', [
            'participants' => $this->publicParticipants($room, $phase),
            'phase' => $phase,
            'roomId' => $room->id,
            'deck' => $this->deckBuilder->buildForRoom($room),
            'room' => $room,
            'consensus' => $consensus,
            'storyQueue' => $room->storyQueue,
            'storyTitle' => $room->getStoryTitle(),
            'recapRows' => $room->storyQueue->recapRows(),
            'roundedMedianLabel' => $consensus?->hasNumericConsensus()
                ? $this->consensusCalculator->roundedMedianLabel($room)
                : null,
            'refreshQueueDrawer' => $refreshQueueDrawer,
            'isModerator' => false,
        ]);
    }

    public function renderPhaseChange(
        Room $room,
        bool $revealed = false,
        bool $includeModeratorActions = false,
        ?ConsensusSummary $consensus = null,
    ): string {
        $phase = $this->resolvePhase($room, $revealed);

        return $this->twig->render('@SymfinityPokerPlanner/room/_phase_change.stream.html.twig', [
            'participants' => $this->publicParticipants($room, $phase),
            'phase' => $phase,
            'roomId' => $room->id,
            'deck' => $this->deckBuilder->buildForRoom($room),
            'room' => $room,
            'includeModeratorActions' => $includeModeratorActions,
            'consensus' => $consensus,
            'storyQueue' => $room->storyQueue,
            'storyTitle' => $room->getStoryTitle(),
            'recapRows' => $room->storyQueue->recapRows(),
            'roundedMedianLabel' => $consensus?->hasNumericConsensus()
                ? $this->consensusCalculator->roundedMedianLabel($room)
                : null,
        ]);
    }

    public function renderStory(Room $room): string
    {
        return $this->twig->render('@SymfinityPokerPlanner/room/_story_audience.stream.html.twig', [
            'storyTitle' => $room->getStoryTitle(),
            'storyQueue' => $room->storyQueue,
            'roomId' => $room->id,
        ]);
    }

    public function publishStory(Room $room): void
    {
        $this->publish($room->id, $this->renderStory($room));
    }

    public function topic(string $roomId): string
    {
        return rtrim($this->topicPrefix, '/').'/rooms/'.$roomId;
    }

    private function publish(string $roomId, string $html): void
    {
        try {
            $this->hub->publish(new Update(
                topics: [$this->topic($roomId)],
                data: $html,
                private: false,
            ));
        } catch (\Throwable $exception) {
            $this->logger?->warning('Poker planner Mercure publish skipped for room {roomId}: {message}', [
                'roomId' => $roomId,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function resolvePhase(Room $room, bool $revealed): Phase
    {
        return $revealed || $room->phase === Phase::Revealed ? Phase::Revealed : Phase::Voting;
    }

    /**
     * @return list<PublicParticipantView>
     */
    private function publicParticipants(Room $room, Phase $phase): array
    {
        $participants = [];
        foreach ($room->participants as $participant) {
            $participants[] = PublicParticipantView::fromParticipant($participant, $phase);
        }

        return $participants;
    }
}
