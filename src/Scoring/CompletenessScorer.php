<?php
declare(strict_types=1);

namespace DraftSweeper\Scoring;

final class CompletenessScorer
{
    public function __construct(
        private readonly int $targetWordCount = 800,
    ) {
    }

    public function score(
        int $wordCount,
        bool $hasTitle,
        bool $hasExcerpt,
        bool $hasFeaturedImage,
        int $categoryCount,
        int $tagCount,
    ): float {
        $wordRatio = $this->targetWordCount > 0
            ? min($wordCount / $this->targetWordCount, 1.0)
            : 0.0;
        $termRatio = min(($categoryCount + $tagCount) / 3.0, 1.0);

        return 0.6 * $wordRatio
            + 0.1 * ($hasTitle ? 1.0 : 0.0)
            + 0.1 * ($hasExcerpt ? 1.0 : 0.0)
            + 0.1 * ($hasFeaturedImage ? 1.0 : 0.0)
            + 0.1 * $termRatio;
    }
}
