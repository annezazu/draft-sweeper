<?php
declare(strict_types=1);

namespace DraftSweeper\Ai;

use DraftSweeper\Drafts\DraftSnapshot;
use DraftSweeper\Scoring\Score;

/**
 * Calls a configured AI provider via the WP AI Client (`AiClient`).
 * Falls back to the template generator on any failure so the widget
 * always renders something.
 */
final class AiNudgeGenerator implements NudgeGenerator
{
    public function __construct(
        private readonly AiProviderResolver $resolver,
        private readonly TemplateNudgeGenerator $fallback,
    ) {
    }

    public function generate(DraftSnapshot $draft, Score $score): string
    {
        $provider = $this->resolver->resolve();
        if ($provider === null || ! class_exists('\\WordPress\\AiClient\\AiClient')) {
            return $this->fallback->generate($draft, $score);
        }

        $prompt = $this->buildPrompt($draft, $score);

        try {
            $response = \WordPress\AiClient\AiClient::generateText([
                'provider' => $provider['id'],
                'prompt'   => $prompt,
                'max_tokens' => 80,
            ]);

            $text = is_string($response) ? trim($response) : trim((string) ($response['text'] ?? ''));
            if ($text === '') {
                return $this->fallback->generate($draft, $score);
            }
            return $text;
        } catch (\Throwable $e) {
            return $this->fallback->generate($draft, $score);
        }
    }

    private function buildPrompt(DraftSnapshot $draft, Score $score): string
    {
        $pct = (int) round($score->completeness * 100);
        return <<<PROMPT
You are a kind, concise writing coach helping a blogger return to an abandoned draft.
Write ONE sentence (max 25 words) that motivates them to finish this post.
Be specific to the draft, warm, and a little playful. No emoji. No quotes.

Draft title: {$draft->title}
Days since last edit: {$draft->daysSinceModified}
Estimated completeness: {$pct}%
Excerpt: {$draft->excerpt}
PROMPT;
    }
}
