<?php
declare(strict_types=1);

namespace DraftSweeper\Tests\Dashboard;

use DraftSweeper\Dashboard\DailyPicker;
use DraftSweeper\Drafts\DraftSnapshot;
use DraftSweeper\Scoring\Score;
use DraftSweeper\Scoring\Weights;
use PHPUnit\Framework\TestCase;

final class DailyPickerTest extends TestCase
{
    /** @return array{draft: DraftSnapshot, score: Score} */
    private function row(int $id, float $completeness, float $recency, float $relevance): array
    {
        $draft = new DraftSnapshot($id, "Title $id", '', 500, true, false, false, 0, 0, [], 10, 'x', 'x', 'x', '', '');
        $score = new Score($completeness, $recency, $relevance, new Weights());
        return ['draft' => $draft, 'score' => $score];
    }

    public function test_pick_returns_null_when_empty(): void
    {
        $this->assertNull((new DailyPicker())->pick([]));
    }

    public function test_pick_returns_highest_total_score(): void
    {
        $rows = [
            $this->row(1, 0.2, 0.5, 0.1),
            $this->row(2, 0.9, 0.5, 0.5),
            $this->row(3, 0.4, 0.4, 0.4),
        ];
        $picked = (new DailyPicker())->pick($rows);
        $this->assertNotNull($picked);
        $this->assertSame(2, $picked['draft']->id);
    }

    public function test_top_n_orders_and_caps(): void
    {
        $rows = [
            $this->row(1, 0.2, 0.5, 0.1),
            $this->row(2, 0.9, 0.5, 0.5),
            $this->row(3, 0.4, 0.4, 0.4),
            $this->row(4, 0.7, 0.7, 0.7),
        ];
        $top = (new DailyPicker())->topN($rows, 2);
        $this->assertCount(2, $top);
        $this->assertSame(2, $top[0]['draft']->id);
        $this->assertSame(4, $top[1]['draft']->id);
    }

    public function test_top_n_floor_is_one_for_zero_request(): void
    {
        $rows = [$this->row(1, 0.5, 0.5, 0.5)];
        $this->assertCount(1, (new DailyPicker())->topN($rows, 0));
    }
}
