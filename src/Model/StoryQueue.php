<?php

declare(strict_types=1);

namespace Symfinity\Bundle\PokerPlanner\Model;

use Symfinity\Bundle\PokerPlanner\Support\Coerce;

final class StoryQueue
{
    public const MAX_ITEMS = 50;

    /**
     * @param list<StoryQueueItem> $items
     * @param list<array{title: string, estimate: string, spreadNote: ?string}> $archivedRecapRows
     */
    public function __construct(
        public array $items = [],
        public int $currentIndex = 0,
        public bool $complete = false,
        public array $archivedRecapRows = [],
    ) {
    }

    public static function fromLegacyTitle(string $title): self
    {
        $trimmed = trim($title);
        if ('' === $trimmed) {
            return new self();
        }

        return new self(items: [new StoryQueueItem($trimmed)]);
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $items = [];
        foreach (Coerce::arrayMap($data['items'] ?? null) as $row) {
            if (!is_array($row)) {
                continue;
            }
            /** @var array<string, mixed> $row */
            $items[] = StoryQueueItem::fromArray($row);
        }

        $currentIndex = Coerce::int($data['currentIndex'] ?? 0);
        if ($items !== [] && $currentIndex >= count($items)) {
            $currentIndex = count($items) - 1;
        }

        $complete = Coerce::bool($data['complete'] ?? false);
        if ($complete && [] === $items) {
            $complete = false;
        }

        return new self(
            items: $items,
            currentIndex: max(0, $currentIndex),
            complete: $complete,
            archivedRecapRows: self::normalizeRecapRows($data['archivedRecapRows'] ?? []),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'items' => array_map(static fn (StoryQueueItem $item): array => $item->toArray(), $this->items),
            'currentIndex' => $this->currentIndex,
            'complete' => $this->complete,
            'archivedRecapRows' => $this->archivedRecapRows,
        ];
    }

    public function currentTitle(): string
    {
        $item = $this->currentItem();

        return null !== $item ? $item->title : '';
    }

    public function currentItem(): ?StoryQueueItem
    {
        if ($this->items === []) {
            return null;
        }

        return $this->items[$this->currentIndex] ?? null;
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function positionLabel(): string
    {
        if ($this->complete) {
            return 'Queue complete';
        }

        if ($this->items === []) {
            return '';
        }

        return sprintf('Story %d of %d', $this->currentIndex + 1, count($this->items));
    }

    public function isEditable(): bool
    {
        return !$this->complete;
    }

    public function isAtEnd(): bool
    {
        if ($this->items === []) {
            return true;
        }

        return $this->currentIndex >= count($this->items) - 1;
    }

    public function addStory(string $title): void
    {
        if ($this->complete) {
            throw new \DomainException('Queue is complete.');
        }

        $title = trim($title);
        if ('' === $title) {
            throw new \InvalidArgumentException('Story title is required.');
        }

        if (count($this->items) >= self::MAX_ITEMS) {
            throw new \DomainException('Story queue is full.');
        }

        $this->items[] = new StoryQueueItem($title);

        if (1 === count($this->items)) {
            $this->currentIndex = 0;
        }
    }

    public function setCurrentTitle(string $title): void
    {
        if ($this->complete) {
            throw new \DomainException('Queue is complete.');
        }

        $title = trim($title);
        $item = $this->currentItem();
        if (!$item instanceof StoryQueueItem) {
            if ('' === $title) {
                return;
            }

            $this->items = [new StoryQueueItem($title)];
            $this->currentIndex = 0;

            return;
        }

        $item->title = $title;
    }

    public function recordCurrentEstimate(?string $estimate): void
    {
        $item = $this->currentItem();
        if (!$item instanceof StoryQueueItem) {
            return;
        }

        $item->recordedEstimate = null !== $estimate && '' !== $estimate ? $estimate : null;
    }

    public function advance(): void
    {
        if ($this->items === [] || $this->complete) {
            return;
        }

        if ($this->currentIndex < count($this->items) - 1) {
            ++$this->currentIndex;
        }
    }

    public function markComplete(): void
    {
        $this->archiveRecordedItems();
        $this->items = [];
        $this->currentIndex = 0;
        $this->complete = false;
    }

    public function archiveAndStartNewSession(): void
    {
        $this->items = [];
        $this->currentIndex = 0;
        $this->complete = false;
    }

    public function removeStory(int $index): void
    {
        if ($this->complete) {
            throw new \DomainException('Queue is complete.');
        }

        if ($index < 0 || $index >= count($this->items)) {
            throw new \InvalidArgumentException('Story not found.');
        }

        if ($index < $this->currentIndex) {
            throw new \DomainException('Cannot remove a story that was already estimated.');
        }

        if ($index === $this->currentIndex && null !== $this->currentItem()?->recordedEstimate) {
            throw new \DomainException('Cannot remove the current story after its estimate was recorded.');
        }

        array_splice($this->items, $index, 1);

        if ($this->items === []) {
            $this->currentIndex = 0;

            return;
        }

        if ($index < $this->currentIndex) {
            --$this->currentIndex;
        }

        if ($this->currentIndex >= count($this->items)) {
            $this->currentIndex = count($this->items) - 1;
        }
    }

    /**
     * @return list<array{title: string, estimate: string, spreadNote: ?string}>
     */
    public function recapRows(): array
    {
        $rows = $this->archivedRecapRows;

        foreach ($this->items as $item) {
            if (null === $item->recordedEstimate || '' === $item->recordedEstimate) {
                continue;
            }

            $rows[] = [
                'title' => $item->title,
                'estimate' => $item->recordedEstimate,
                'spreadNote' => null,
            ];
        }

        return $rows;
    }

    private function archiveRecordedItems(): void
    {
        foreach ($this->items as $item) {
            if (null === $item->recordedEstimate || '' === $item->recordedEstimate) {
                continue;
            }

            $this->archivedRecapRows[] = [
                'title' => $item->title,
                'estimate' => $item->recordedEstimate,
                'spreadNote' => null,
            ];
        }
    }

    /**
     * @param mixed $rows
     *
     * @return list<array{title: string, estimate: string, spreadNote: ?string}>
     */
    private static function normalizeRecapRows(mixed $rows): array
    {
        if (!is_array($rows)) {
            return [];
        }

        $normalized = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $title = trim(Coerce::string($row['title'] ?? null));
            $estimate = trim(Coerce::string($row['estimate'] ?? null));
            if ('' === $title || '' === $estimate) {
                continue;
            }

            $spreadNote = $row['spreadNote'] ?? null;
            $normalized[] = [
                'title' => $title,
                'estimate' => $estimate,
                'spreadNote' => is_string($spreadNote) && '' !== $spreadNote ? $spreadNote : null,
            ];
        }

        return $normalized;
    }
}
