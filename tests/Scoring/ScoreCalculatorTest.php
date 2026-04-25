<?php
declare(strict_types=1);

namespace DraftSweeper\Tests\Scoring;

use DraftSweeper\Drafts\DraftSnapshot;
use DraftSweeper\Scoring\ScoreCalculator;
use DraftSweeper\Scoring\Weights;
use PHPUnit\Framework\TestCase;

final class ScoreCalculatorTest extends TestCase
{
    public function test_full_draft_with_matching_terms_scores_near_one(): void
    {
        $snapshot = new DraftSnapshot(
            id: 1,
            title: 'My Post',
            editLink: 'https://example.test/wp-admin/post.php?post=1&action=edit',
            wordCount: 800,
            hasTitle: true,
            hasExcerpt: true,
            hasFeaturedImage: true,
            categoryCount: 1,
            tagCount: 2,
            termIds: [10, 20, 30],
            daysSinceModified: 60,
            modifiedHuman: '2 months',
            startedHuman: '3 months',
            excerpt: '...',
        );

        $calc = new ScoreCalculator(new Weights(0.5, 0.2, 0.3));
        $score = $calc->calculate($snapshot, [10 => 1, 20 => 1, 30 => 1]);

        $this->assertEqualsWithDelta(1.0, $score->total, 0.001);
    }

    public function test_brand_new_empty_draft_scores_zero(): void
    {
        $snapshot = new DraftSnapshot(
            1, '', '', 0, false, false, false, 0, 0, [], 0, 'just now', 'just now', ''
        );
        $calc = new ScoreCalculator(new Weights(0.5, 0.2, 0.3));
        $this->assertSame(0.0, $calc->calculate($snapshot, [])->total);
    }
}
