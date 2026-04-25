<?php
declare(strict_types=1);

namespace DraftSweeper\Ai;

use DraftSweeper\Drafts\DraftSnapshot;

interface SummaryGenerator
{
    /**
     * One-sentence "what this draft is about" line. Should be plain text, ≤140 chars.
     */
    public function summarize(DraftSnapshot $draft): string;
}
