<?php

declare(strict_types=1);

namespace Symfinity\Bundle\PokerPlanner\Model;

/**
 * Read model computed on reveal — not persisted separately.
 */
final class ConsensusSummary
{
    /**
     * @param list<string> $outlierParticipantIds
     */
    public function __construct(
        public readonly int $count,
        public readonly ?float $median,
        public readonly ?float $spread,
        public readonly array $outlierParticipantIds,
    ) {
    }

    public function hasNumericConsensus(): bool
    {
        return $this->count > 0 && null !== $this->median;
    }

    public function hasZeroSpread(): bool
    {
        if (!$this->hasNumericConsensus() || null === $this->spread) {
            return false;
        }

        return abs($this->spread) < 0.001;
    }

    public function medianLabel(): ?string
    {
        if (null === $this->median) {
            return null;
        }

        if (abs($this->median - 0.5) < 0.001) {
            return '½';
        }

        if (abs($this->median - round($this->median)) < 0.001) {
            return (string) (int) round($this->median);
        }

        return rtrim(rtrim(number_format($this->median, 1, '.', ''), '0'), '.');
    }

    public function statusMessage(): string
    {
        if (!$this->hasNumericConsensus()) {
            return 'No numeric consensus';
        }

        $label = $this->medianLabel() ?? (string) $this->median;

        return sprintf('Median %s · spread %s', $label, $this->formatNumber($this->spread ?? 0.0));
    }

    private function formatNumber(float $value): string
    {
        if (abs($value - round($value)) < 0.001) {
            return (string) (int) round($value);
        }

        return rtrim(rtrim(number_format($value, 1, '.', ''), '0'), '.');
    }
}
