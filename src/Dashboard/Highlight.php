<?php
declare(strict_types=1);

namespace DraftSweeper\Dashboard;

use DraftSweeper\Drafts\DraftSnapshot;
use DraftSweeper\Scoring\Score;

/**
 * Pure helper that turns a (draft, score) pair into UI hints:
 *  - a short "reason" badge that tells the writer why this draft was surfaced
 *  - an estimated minutes-to-finish (only useful when the draft is mostly done)
 */
final class Highlight
{
    public const REASON_ALMOST_DONE   = 'almost_done';
    public const REASON_HALF_WRITTEN  = 'half_written';
    public const REASON_BURIED        = 'buried_treasure';
    public const REASON_ON_TREND      = 'on_trend';
    public const REASON_FRESH_SPARK   = 'fresh_spark';

    public function __construct(
        private readonly int $targetWordCount = 800,
        private readonly int $wordsPerMinute = 30,
    ) {
    }

    public function reason(DraftSnapshot $draft, Score $score): string
    {
        if ($score->completeness >= 0.8) {
            return self::REASON_ALMOST_DONE;
        }
        if ($score->relevance >= 0.5 && $score->completeness >= 0.4) {
            return self::REASON_ON_TREND;
        }
        if ($draft->daysSinceModified >= 365 && $score->completeness >= 0.4) {
            return self::REASON_BURIED;
        }
        if ($score->completeness >= 0.4) {
            return self::REASON_HALF_WRITTEN;
        }
        return self::REASON_FRESH_SPARK;
    }

    /**
     * Estimated minutes to finish based on word count delta against the target.
     * Returns null when the draft is already at/over target.
     */
    public function minutesToFinish(DraftSnapshot $draft): ?int
    {
        $remaining = $this->targetWordCount - $draft->wordCount;
        if ($remaining <= 0 || $this->wordsPerMinute <= 0) {
            return null;
        }
        return (int) max(1, ceil($remaining / $this->wordsPerMinute));
    }
}
