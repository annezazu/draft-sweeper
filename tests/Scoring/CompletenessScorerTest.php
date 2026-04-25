<?php
declare(strict_types=1);

namespace DraftSweeper\Tests\Scoring;

use DraftSweeper\Scoring\CompletenessScorer;
use PHPUnit\Framework\TestCase;

final class CompletenessScorerTest extends TestCase
{
    public function test_empty_draft_scores_zero(): void
    {
        $s = new CompletenessScorer();
        $this->assertSame(0.0, $s->score(0, false, false, false, 0, 0));
    }

    public function test_full_draft_scores_one(): void
    {
        $s = new CompletenessScorer(800);
        $this->assertEqualsWithDelta(1.0, $s->score(800, true, true, true, 1, 2), 1e-9);
    }

    public function test_overlong_draft_caps_at_full_word_credit(): void
    {
        $s = new CompletenessScorer(800);
        $this->assertEqualsWithDelta(1.0, $s->score(5000, true, true, true, 5, 5), 1e-9);
    }

    public function test_title_only_short_draft(): void
    {
        $s = new CompletenessScorer(800);
        // 400 words = 0.5 of word target; word weight 0.6 -> 0.30; title 0.10 -> 0.40
        $this->assertEqualsWithDelta(0.40, $s->score(400, true, false, false, 0, 0), 0.001);
    }
}
