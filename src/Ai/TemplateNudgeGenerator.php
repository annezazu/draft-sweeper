<?php
declare(strict_types=1);

namespace DraftSweeper\Ai;

use DraftSweeper\Drafts\DraftSnapshot;
use DraftSweeper\Scoring\Score;

final class TemplateNudgeGenerator implements NudgeGenerator
{
    public function generate(DraftSnapshot $draft, Score $score): string
    {
        $pct = (int) round($score->completeness * 100);
        $age = $this->humanizeAge($draft->daysSinceModified);

        if ($pct >= 80) {
            return sprintf(
                /* translators: 1: human age (e.g. "6 months"), 2: completeness percentage */
                __('You started this %1$s ago and it\'s %2$d%% done — finishing it would be a quick win.', 'draft-sweeper'),
                $age,
                $pct
            );
        }

        if ($pct >= 40) {
            return sprintf(
                __('Half-finished from %1$s ago. The hardest part — getting started — is behind you.', 'draft-sweeper'),
                $age
            );
        }

        return sprintf(
            __('A spark from %1$s ago. Worth a fresh look while the idea\'s still interesting.', 'draft-sweeper'),
            $age
        );
    }

    private function humanizeAge(int $days): string
    {
        if ($days < 14) {
            return sprintf(_n('%d day', '%d days', max(1, $days), 'draft-sweeper'), max(1, $days));
        }
        if ($days < 60) {
            $w = (int) round($days / 7);
            return sprintf(_n('%d week', '%d weeks', $w, 'draft-sweeper'), $w);
        }
        if ($days < 730) {
            $m = (int) round($days / 30);
            return sprintf(_n('%d month', '%d months', $m, 'draft-sweeper'), $m);
        }
        $y = (int) round($days / 365);
        return sprintf(_n('%d year', '%d years', $y, 'draft-sweeper'), $y);
    }
}
