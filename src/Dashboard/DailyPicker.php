<?php
declare(strict_types=1);

namespace DraftSweeper\Dashboard;

use DraftSweeper\Drafts\DraftSnapshot;
use DraftSweeper\Scoring\Score;

/**
 * Pure helper that picks today's single draft from a scored list.
 * Sort order = highest total score first; ties keep input order.
 *
 * Inputs are already filtered (no dismissed drafts), so the picker
 * just orders and returns the top one — or null if the list is empty.
 *
 * @phpstan-type Scored array{draft: DraftSnapshot, score: Score}
 */
final class DailyPicker
{
    /**
     * @param list<Scored> $scored
     * @return Scored|null
     */
    public function pick(array $scored): ?array
    {
        if ($scored === []) {
            return null;
        }
        usort($scored, static fn($a, $b) => $b['score']->total <=> $a['score']->total);
        return $scored[0];
    }

    /**
     * Returns the top N candidates (by score) for handoff to the AI picker.
     *
     * @param list<Scored> $scored
     * @param int $n
     * @return list<Scored>
     */
    public function topN(array $scored, int $n): array
    {
        usort($scored, static fn($a, $b) => $b['score']->total <=> $a['score']->total);
        return array_slice($scored, 0, max(1, $n));
    }
}
