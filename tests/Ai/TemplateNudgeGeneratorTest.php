<?php
declare(strict_types=1);

namespace DraftSweeper\Tests\Ai;

use DraftSweeper\Ai\TemplateNudgeGenerator;
use DraftSweeper\Drafts\DraftSnapshot;
use DraftSweeper\Scoring\Score;
use DraftSweeper\Scoring\Weights;
use PHPUnit\Framework\TestCase;

final class TemplateNudgeGeneratorTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        // Stub WP i18n helpers used by the template generator so it runs unit-test only.
        if (! function_exists('__')) {
            eval('function __($s, $d = null) { return $s; }');
        }
        if (! function_exists('_n')) {
            eval('function _n($s, $p, $n, $d = null) { return $n === 1 ? $s : $p; }');
        }
    }

    public function test_high_completeness_uses_quick_win_copy(): void
    {
        $snap = new DraftSnapshot(1, 'Foo', '', 700, true, true, true, 1, 1, [1], 180, '', '');
        $score = new Score(0.9, 1.0, 0.5, new Weights());
        $msg = (new TemplateNudgeGenerator())->generate($snap, $score);
        $this->assertStringContainsString('quick win', $msg);
        $this->assertStringContainsString('90%', $msg);
        $this->assertStringContainsString('months', $msg);
    }

    public function test_low_completeness_uses_spark_copy(): void
    {
        $snap = new DraftSnapshot(1, 'Foo', '', 50, true, false, false, 0, 0, [], 400, '', '');
        $score = new Score(0.1, 1.0, 0.0, new Weights());
        $msg = (new TemplateNudgeGenerator())->generate($snap, $score);
        $this->assertStringContainsString('spark', $msg);
    }
}
