<?php

declare(strict_types=1);

namespace Symfinity\Bundle\PokerPlanner\Model;

use Symfinity\Bundle\PokerPlanner\Support\Coerce;

final class StoryQueueItem
{
    public function __construct(
        public string $title,
        public ?string $recordedEstimate = null,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            title: Coerce::string($data['title'] ?? null),
            recordedEstimate: isset($data['recordedEstimate']) && is_string($data['recordedEstimate'])
                ? $data['recordedEstimate']
                : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'recordedEstimate' => $this->recordedEstimate,
        ];
    }
}
