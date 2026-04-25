<?php
declare(strict_types=1);

namespace DraftSweeper\Tests\Scoring;

use DraftSweeper\Scoring\Score;
use DraftSweeper\Scoring\Weights;
use PHPUnit\Framework\TestCase;

final class ScoreTest extends TestCase
{
    public function test_total_is_weighted_sum(): void
    {
        $weights = new Weights(0.5, 0.2, 0.3);
        $score = new Score(1.0, 0.5, 0.0, $weights);

        $this->assertSame(0.5 * 1.0 + 0.2 * 0.5 + 0.3 * 0.0, $score->total);
    }

    public function test_components_are_clamped_to_unit_interval(): void
    {
        $score = new Score(1.5, -0.2, 0.3, new Weights(0.5, 0.2, 0.3));
        $this->assertSame(1.0, $score->completeness);
        $this->assertSame(0.0, $score->recency);
    }
}
