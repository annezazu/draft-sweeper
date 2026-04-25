<?php
declare(strict_types=1);

namespace DraftSweeper\Scoring;

final class Weights
{
    public function __construct(
        public readonly float $completeness = 0.5,
        public readonly float $recency = 0.2,
        public readonly float $relevance = 0.3,
    ) {
    }

    public function normalized(): self
    {
        $sum = $this->completeness + $this->recency + $this->relevance;
        if ($sum <= 0.0) {
            return new self();
        }
        return new self(
            $this->completeness / $sum,
            $this->recency / $sum,
            $this->relevance / $sum,
        );
    }
}
