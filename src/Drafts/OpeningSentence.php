<?php
declare(strict_types=1);

namespace DraftSweeper\Drafts;

/**
 * Pulls the first complete sentence from raw draft content as a teaser.
 * Returns '' when no usable text is found.
 */
final class OpeningSentence
{
    public function __construct(
        private readonly int $maxChars = 180,
    ) {
    }

    public function extract(string $content): string
    {
        $text = trim(preg_replace('/\s+/', ' ', $content) ?? '');
        if ($text === '') {
            return '';
        }

        // Find the first sentence-ending punctuation followed by a space or end-of-string.
        if (preg_match('/^(.+?[.!?])(?:\s|$)/u', $text, $m) === 1) {
            $sentence = trim($m[1]);
        } else {
            $sentence = $text;
        }

        if (function_exists('mb_strlen') && mb_strlen($sentence) > $this->maxChars) {
            return mb_strimwidth($sentence, 0, $this->maxChars, '…');
        }
        if (strlen($sentence) > $this->maxChars) {
            return substr($sentence, 0, $this->maxChars - 1) . '…';
        }
        return $sentence;
    }
}
