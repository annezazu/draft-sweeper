<?php
declare(strict_types=1);

namespace DraftSweeper\Ai;

use DraftSweeper\Drafts\DraftSnapshot;
use DraftSweeper\Scoring\Score;

interface NudgeGenerator
{
    public function generate(DraftSnapshot $draft, Score $score): string;
}
