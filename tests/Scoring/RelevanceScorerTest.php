<?php
declare(strict_types=1);

namespace DraftSweeper\Tests\Scoring;

use DraftSweeper\Scoring\RelevanceScorer;
use PHPUnit\Framework\TestCase;

final class RelevanceScorerTest extends TestCase
{
    public function test_uniformly_weighted_identical_term_sets_score_one(): void
    {
        $s = new RelevanceScorer();
        // Draft is a presence vector; recent uses uniform weights -> perfect cosine alignment.
        $this->assertEqualsWithDelta(1.0, $s->score([1, 2, 3], [1 => 1, 2 => 1, 3 => 1]), 0.001);
    }

    public function test_disjoint_term_sets_score_zero(): void
    {
        $s = new RelevanceScorer();
        $this->assertSame(0.0, $s->score([1, 2], [3 => 1, 4 => 1]));
    }

    public function test_partial_overlap(): void
    {
        $s = new RelevanceScorer();
        // Draft has [1,2]; recent has weights {1:1, 2:1, 3:1}
        // cosine = (1*1 + 1*1 + 0*1) / (sqrt(2) * sqrt(3)) = 2 / sqrt(6) ≈ 0.8165
        $this->assertEqualsWithDelta(0.8165, $s->score([1, 2], [1 => 1, 2 => 1, 3 => 1]), 0.001);
    }

    public function test_empty_draft_terms_scores_zero(): void
    {
        $this->assertSame(0.0, (new RelevanceScorer())->score([], [1 => 1]));
    }

    public function test_empty_recent_terms_scores_zero(): void
    {
        $this->assertSame(0.0, (new RelevanceScorer())->score([1], []));
    }
}
