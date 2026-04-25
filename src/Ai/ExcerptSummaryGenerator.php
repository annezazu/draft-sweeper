<?php
declare(strict_types=1);

namespace DraftSweeper\Ai;

use DraftSweeper\Drafts\DraftSnapshot;

/**
 * Non-AI fallback: returns a short content extract.
 */
final class ExcerptSummaryGenerator implements SummaryGenerator
{
    public function __construct(private readonly int $maxChars = 140)
    {
    }

    public function summarize(DraftSnapshot $draft): string
    {
        $text = trim($draft->excerpt);
        if ($text === '') {
            return '';
        }
        if (function_exists('mb_strimwidth')) {
            return mb_strimwidth($text, 0, $this->maxChars, '…');
        }
        return strlen($text) > $this->maxChars ? substr($text, 0, $this->maxChars - 1) . '…' : $text;
    }
}
