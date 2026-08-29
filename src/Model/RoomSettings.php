<?php

declare(strict_types=1);

namespace Symfinity\Bundle\PokerPlanner\Model;

use Symfinity\Bundle\PokerPlanner\Support\Coerce;

final class RoomSettings
{
    public function __construct(
        public string $teamName = '',
        public bool $saved = false,
        public DeckPreset $deckPreset = DeckPreset::ModifiedFibonacci,
        public bool $optionalZero = false,
        public bool $optionalPass = false,
        public bool $optionalBreak = false,
        public RoundingMode $roundingMode = RoundingMode::Nearest,
        public bool $allowChangeAfterReveal = false,
        public bool $showConfetti = true,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $preset = DeckPreset::tryFrom(Coerce::string($data['deckPreset'] ?? null)) ?? DeckPreset::ModifiedFibonacci;
        $rounding = RoundingMode::tryFrom(Coerce::string($data['roundingMode'] ?? null)) ?? RoundingMode::Nearest;

        return new self(
            teamName: trim(Coerce::string($data['teamName'] ?? null)),
            saved: Coerce::bool($data['saved'] ?? false),
            deckPreset: $preset,
            optionalZero: Coerce::bool($data['optionalZero'] ?? false),
            optionalPass: Coerce::bool($data['optionalPass'] ?? false),
            optionalBreak: Coerce::bool($data['optionalBreak'] ?? false),
            roundingMode: $rounding,
            allowChangeAfterReveal: Coerce::bool($data['allowChangeAfterReveal'] ?? false),
            showConfetti: Coerce::bool($data['showConfetti'] ?? true, true),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'teamName' => $this->teamName,
            'saved' => $this->saved,
            'deckPreset' => $this->deckPreset->value,
            'optionalZero' => $this->optionalZero,
            'optionalPass' => $this->optionalPass,
            'optionalBreak' => $this->optionalBreak,
            'roundingMode' => $this->roundingMode->value,
            'allowChangeAfterReveal' => $this->allowChangeAfterReveal,
            'showConfetti' => $this->showConfetti,
        ];
    }

    public function displayTitle(): string
    {
        if ('' !== $this->teamName) {
            return $this->teamName;
        }

        return 'quick room';
    }
}
