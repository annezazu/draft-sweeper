<?php
declare(strict_types=1);

namespace DraftSweeper\Drafts;

/**
 * Turns a timestamp into evocative phrasing instead of "X months ago".
 *
 *   < 7 days     -> "earlier this week"
 *   < 30 days    -> "from a <Weekday> in <Month>"
 *   same year    -> "from <Month>"
 *   last year    -> "from last <season>" or "from <Month> last year"
 *   older        -> "from <Month> <Year>"
 */
final class EvocativeDate
{
    public function __construct(
        private readonly int $now,
    ) {
    }

    public function describe(int $timestamp): string
    {
        $diffSeconds = $this->now - $timestamp;
        $diffDays = (int) floor($diffSeconds / 86400);

        if ($diffDays < 0) {
            return 'just now';
        }
        if ($diffDays < 7) {
            return 'earlier this week';
        }
        if ($diffDays < 30) {
            $weekday = gmdate('l', $timestamp);
            $month   = gmdate('F', $timestamp);
            return "from a {$weekday} in {$month}";
        }

        $thisYear = (int) gmdate('Y', $this->now);
        $thatYear = (int) gmdate('Y', $timestamp);
        $month    = gmdate('F', $timestamp);

        if ($thatYear === $thisYear) {
            return "from {$month}";
        }
        if ($thatYear === $thisYear - 1) {
            $season = $this->season($timestamp);
            if ($season !== null) {
                return "from last {$season}";
            }
            return "from {$month} last year";
        }
        return "from {$month} {$thatYear}";
    }

    private function season(int $timestamp): ?string
    {
        $month = (int) gmdate('n', $timestamp);
        return match (true) {
            in_array($month, [12, 1, 2], true)  => 'winter',
            in_array($month, [3, 4, 5], true)   => 'spring',
            in_array($month, [6, 7, 8], true)   => 'summer',
            in_array($month, [9, 10, 11], true) => 'fall',
            default                             => null,
        };
    }
}
