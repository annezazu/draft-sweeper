<?php
declare(strict_types=1);

namespace DraftSweeper\Tests\Drafts;

use DraftSweeper\Drafts\OpeningSentence;
use PHPUnit\Framework\TestCase;

final class OpeningSentenceTest extends TestCase
{
    public function test_returns_first_sentence(): void
    {
        $content = "It was the summer I learned to cook. The kitchen was small but well-lit.";
        $this->assertSame(
            'It was the summer I learned to cook.',
            (new OpeningSentence())->extract($content)
        );
    }

    public function test_handles_question_and_exclamation(): void
    {
        $this->assertSame('What if?', (new OpeningSentence())->extract('What if? More follows.'));
        $this->assertSame('Wow!', (new OpeningSentence())->extract('Wow! Then this.'));
    }

    public function test_collapses_whitespace_and_newlines(): void
    {
        $content = "First line\n\nstill the same sentence. Second sentence.";
        $this->assertSame(
            'First line still the same sentence.',
            (new OpeningSentence())->extract($content)
        );
    }

    public function test_truncates_when_no_sentence_boundary(): void
    {
        $content = str_repeat('word ', 100);
        $out = (new OpeningSentence(50))->extract($content);
        $this->assertSame(50, mb_strlen($out));
        $this->assertStringEndsWith('…', $out);
    }

    public function test_empty_input_returns_empty(): void
    {
        $this->assertSame('', (new OpeningSentence())->extract(''));
        $this->assertSame('', (new OpeningSentence())->extract('   '));
    }
}
