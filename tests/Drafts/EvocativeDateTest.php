<?php
declare(strict_types=1);

namespace DraftSweeper\Tests\Drafts;

use DraftSweeper\Drafts\EvocativeDate;
use PHPUnit\Framework\TestCase;

final class EvocativeDateTest extends TestCase
{
    public function test_recent_drafts_say_earlier_this_week(): void
    {
        $now = strtotime('2026-04-26 00:00:00 UTC');
        $ts = strtotime('2026-04-23 12:00:00 UTC');
        $this->assertSame('earlier this week', (new EvocativeDate($now))->describe($ts));
    }

    public function test_within_a_month_uses_weekday_and_month(): void
    {
        $now = strtotime('2026-04-26 00:00:00 UTC');
        $ts = strtotime('2026-04-07 12:00:00 UTC'); // Tuesday
        $this->assertSame('from a Tuesday in April', (new EvocativeDate($now))->describe($ts));
    }

    public function test_same_year_uses_month_only(): void
    {
        $now = strtotime('2026-04-26 00:00:00 UTC');
        $ts = strtotime('2026-01-15 12:00:00 UTC');
        $this->assertSame('from January', (new EvocativeDate($now))->describe($ts));
    }

    public function test_last_year_uses_season(): void
    {
        $now = strtotime('2026-04-26 00:00:00 UTC');
        $ts = strtotime('2025-10-01 12:00:00 UTC');
        $this->assertSame('from last fall', (new EvocativeDate($now))->describe($ts));
    }

    public function test_older_uses_month_and_year(): void
    {
        $now = strtotime('2026-04-26 00:00:00 UTC');
        $ts = strtotime('2022-08-15 12:00:00 UTC');
        $this->assertSame('from August 2022', (new EvocativeDate($now))->describe($ts));
    }
}
