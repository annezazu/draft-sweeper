<?php
declare(strict_types=1);

namespace DraftSweeper\Tests\Ai;

use DraftSweeper\Ai\ExcerptSummaryGenerator;
use DraftSweeper\Drafts\DraftSnapshot;
use PHPUnit\Framework\TestCase;

final class ExcerptSummaryGeneratorTest extends TestCase
{
    public function test_returns_excerpt_truncated(): void
    {
        $excerpt = str_repeat('word ', 100); // 500 chars
        $snap = new DraftSnapshot(1, 't', '', 100, true, false, false, 0, 0, [], 1, '1d', '1d', '1d', '', $excerpt);
        $out = (new ExcerptSummaryGenerator(140))->summarize($snap);
        $this->assertSame(140, mb_strlen($out));
        $this->assertStringEndsWith('…', $out);
    }

    public function test_short_excerpt_passes_through(): void
    {
        $snap = new DraftSnapshot(1, 't', '', 5, true, false, false, 0, 0, [], 1, '1d', '1d', '1d', '', 'short text here');
        $this->assertSame('short text here', (new ExcerptSummaryGenerator())->summarize($snap));
    }

    public function test_empty_excerpt_returns_empty(): void
    {
        $snap = new DraftSnapshot(1, 't', '', 0, true, false, false, 0, 0, [], 1, '1d', '1d', '1d', '', '');
        $this->assertSame('', (new ExcerptSummaryGenerator())->summarize($snap));
    }
}
