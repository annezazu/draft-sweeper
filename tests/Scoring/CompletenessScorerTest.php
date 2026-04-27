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

    public function test_components_for_empty_draft_are_all_unmet(): void
    {
        $s = new CompletenessScorer(800);
        $this->assertSame(
            ['words' => false, 'title' => false, 'excerpt' => false, 'image' => false, 'terms' => false],
            $s->components(0, false, false, false, 0, 0),
        );
    }

    public function test_components_for_full_draft_are_all_met(): void
    {
        $s = new CompletenessScorer(800);
        $this->assertSame(
            ['words' => true, 'title' => true, 'excerpt' => true, 'image' => true, 'terms' => true],
            $s->components(800, true, true, true, 1, 2),
        );
    }

    public function test_components_words_met_only_at_or_above_target(): void
    {
        $s = new CompletenessScorer(800);
        $this->assertFalse($s->components(799, true, true, true, 1, 2)['words']);
        $this->assertTrue($s->components(800, true, true, true, 1, 2)['words']);
    }

    public function test_components_terms_met_only_at_three_or_more(): void
    {
        $s = new CompletenessScorer(800);
        $this->assertFalse($s->components(800, true, true, true, 1, 1)['terms']);
        $this->assertTrue($s->components(800, true, true, true, 2, 1)['terms']);
    }
}
