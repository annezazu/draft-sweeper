<?php
declare(strict_types=1);

namespace DraftSweeper\Scoring;

final class Score
{
    public readonly float $completeness;
    public readonly float $recency;
    public readonly float $relevance;
    public readonly float $total;

    public function __construct(
        float $completeness,
        float $recency,
        float $relevance,
        public readonly Weights $weights,
    ) {
        $this->completeness = self::clamp($completeness);
        $this->recency = self::clamp($recency);
        $this->relevance = self::clamp($relevance);
        $this->total =
            $weights->completeness * $this->completeness +
            $weights->recency * $this->recency +
            $weights->relevance * $this->relevance;
    }

    private static function clamp(float $v): float
    {
        return max(0.0, min(1.0, $v));
    }
}
