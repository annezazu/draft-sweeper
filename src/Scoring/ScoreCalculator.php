<?php
declare(strict_types=1);

namespace DraftSweeper\Scoring;

use DraftSweeper\Drafts\DraftSnapshot;

final class ScoreCalculator
{
    private CompletenessScorer $completeness;
    private RecencyScorer $recency;
    private RelevanceScorer $relevance;

    public function __construct(
        private readonly Weights $weights = new Weights(),
    ) {
        $this->completeness = new CompletenessScorer();
        $this->recency = new RecencyScorer();
        $this->relevance = new RelevanceScorer();
    }

    /**
     * @param array<int, int> $recentTermFreq
     */
    public function calculate(DraftSnapshot $d, array $recentTermFreq): Score
    {
        return new Score(
            completeness: $this->completeness->score(
                $d->wordCount,
                $d->hasTitle,
                $d->hasExcerpt,
                $d->hasFeaturedImage,
                $d->categoryCount,
                $d->tagCount,
            ),
            recency: $this->recency->score($d->daysSinceModified),
            relevance: $this->relevance->score($d->termIds, $recentTermFreq),
            weights: $this->weights,
        );
    }
}
