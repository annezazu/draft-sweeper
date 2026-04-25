<?php
declare(strict_types=1);

namespace DraftSweeper\Scoring;

final class RecencyScorer
{
    public function __construct(
        private readonly int $rampDays = 30,
    ) {
    }

    /**
     * Linear ramp from 0 (today) up to 1.0 at $rampDays, then plateaus.
     * Ancient drafts stay at 1.0 — we want to surface them.
     */
    public function score(int $daysSinceModified): float
    {
        if ($daysSinceModified <= 0 || $this->rampDays <= 0) {
            return 0.0;
        }
        return min($daysSinceModified / $this->rampDays, 1.0);
    }
}
