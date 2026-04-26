<?php
declare(strict_types=1);

namespace DraftSweeper\Tests\Dashboard;

use DraftSweeper\Dashboard\Highlight;
use DraftSweeper\Drafts\DraftSnapshot;
use DraftSweeper\Scoring\Score;
use DraftSweeper\Scoring\Weights;
use PHPUnit\Framework\TestCase;

final class HighlightTest extends TestCase
{
    private function snap(int $words, int $days): DraftSnapshot
    {
        return new DraftSnapshot(1, 'Title', '', $words, true, false, false, 0, 0, [], $days, 'x', 'x', '');
    }

    public function test_almost_done_takes_priority(): void
    {
        $score = new Score(0.9, 1.0, 0.0, new Weights());
        $this->assertSame(Highlight::REASON_ALMOST_DONE, (new Highlight())->reason($this->snap(700, 30), $score));
    }

    public function test_on_trend_when_relevance_high_and_partial(): void
    {
        $score = new Score(0.6, 1.0, 0.7, new Weights());
        $this->assertSame(Highlight::REASON_ON_TREND, (new Highlight())->reason($this->snap(400, 30), $score));
    }

    public function test_buried_treasure_for_old_partial_drafts(): void
    {
        $score = new Score(0.5, 1.0, 0.0, new Weights());
        $this->assertSame(Highlight::REASON_BURIED, (new Highlight())->reason($this->snap(400, 540), $score));
    }

    public function test_fresh_spark_for_short_drafts(): void
    {
        $score = new Score(0.1, 0.5, 0.0, new Weights());
        $this->assertSame(Highlight::REASON_FRESH_SPARK, (new Highlight())->reason($this->snap(80, 5), $score));
    }

    public function test_minutes_to_finish_caps_at_one(): void
    {
        $h = new Highlight(targetWordCount: 800, wordsPerMinute: 30);
        $this->assertSame(1, $h->minutesToFinish($this->snap(799, 1)));
        $this->assertSame(7, $h->minutesToFinish($this->snap(600, 1)));
        $this->assertNull($h->minutesToFinish($this->snap(800, 1)));
        $this->assertNull($h->minutesToFinish($this->snap(1200, 1)));
    }
}
