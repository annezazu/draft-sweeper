<?php
declare(strict_types=1);

namespace DraftSweeper\Tests\Scoring;

use DraftSweeper\Scoring\RecencyScorer;
use PHPUnit\Framework\TestCase;

final class RecencyScorerTest extends TestCase
{
    public function test_today_scores_zero(): void
    {
        $this->assertSame(0.0, (new RecencyScorer())->score(0));
    }

    public function test_thirty_days_scores_one(): void
    {
        $this->assertSame(1.0, (new RecencyScorer())->score(30));
    }

    public function test_ancient_drafts_stay_at_one(): void
    {
        $s = new RecencyScorer();
        $this->assertSame(1.0, $s->score(365));
        $this->assertSame(1.0, $s->score(3650));
    }

    public function test_partial_ramp(): void
    {
        $this->assertEqualsWithDelta(0.5, (new RecencyScorer())->score(15), 0.001);
    }
}
