<?php
declare(strict_types=1);

namespace DraftSweeper\Ai;

use DraftSweeper\Dashboard\DailyPicker;
use DraftSweeper\Drafts\DraftSnapshot;

/**
 * When an AI provider is configured, this picker hands the top deterministic
 * candidates to the model and asks it to choose the single most resonant one
 * for today, returning a short "why this one" nudge.
 *
 * Token-budget conscious: we send at most 3 candidates, each truncated to
 * ~200 chars of excerpt, plus up to 5 recent topic terms for context. The
 * AI response is constrained to a JSON object with two short fields.
 *
 * Falls back to deterministic top-1 when no provider is available, the
 * AiClient class is missing, or the response can't be parsed.
 *
 * @phpstan-type Scored array{draft: DraftSnapshot, score: \DraftSweeper\Scoring\Score}
 */
final class AiDailyPicker
{
    private const MAX_CANDIDATES = 3;
    private const EXCERPT_CHARS  = 200;
    private const MAX_TOKENS     = 160;

    public function __construct(
        private readonly AiProviderResolver $resolver,
        private readonly DailyPicker $deterministic,
    ) {
    }

    /**
     * @param list<Scored> $scored
     * @param list<string> $recentTopicLabels  e.g. ['gardening', 'WordPress', 'travel']
     * @return array{draft: DraftSnapshot, score: \DraftSweeper\Scoring\Score, nudge: string}|null
     */
    public function pick(array $scored, array $recentTopicLabels = []): ?array
    {
        $candidates = $this->deterministic->topN($scored, self::MAX_CANDIDATES);
        if ($candidates === []) {
            return null;
        }

        $provider = $this->resolver->resolve();
        if ($provider === null || ! class_exists('\\WordPress\\AiClient\\AiClient') || count($candidates) === 1) {
            $top = $candidates[0];
            return ['draft' => $top['draft'], 'score' => $top['score'], 'nudge' => ''];
        }

        $prompt = $this->buildPrompt($candidates, $recentTopicLabels);

        try {
            $response = \WordPress\AiClient\AiClient::generateText([
                'provider'   => $provider['id'],
                'prompt'     => $prompt,
                'max_tokens' => self::MAX_TOKENS,
            ]);
            $text = is_string($response) ? $response : (string) ($response['text'] ?? '');
            $parsed = $this->parse($text);
            if ($parsed === null) {
                $top = $candidates[0];
                return ['draft' => $top['draft'], 'score' => $top['score'], 'nudge' => ''];
            }

            foreach ($candidates as $row) {
                if ($row['draft']->id === $parsed['post_id']) {
                    return ['draft' => $row['draft'], 'score' => $row['score'], 'nudge' => $parsed['nudge']];
                }
            }
        } catch (\Throwable) {
            // fall through
        }

        $top = $candidates[0];
        return ['draft' => $top['draft'], 'score' => $top['score'], 'nudge' => ''];
    }

    /**
     * @param list<Scored> $candidates
     * @param list<string> $topics
     */
    private function buildPrompt(array $candidates, array $topics): string
    {
        $lines = [];
        foreach ($candidates as $row) {
            $d = $row['draft'];
            $excerpt = $this->trimExcerpt($d->excerpt !== '' ? $d->excerpt : $d->openingSentence);
            $title = $d->hasTitle ? $d->title : '(untitled)';
            $lines[] = sprintf('- id=%d | %d words | %s | %s', $d->id, $d->wordCount, $title, $excerpt);
        }
        $candidateBlock = implode("\n", $lines);
        $topicBlock = $topics === []
            ? '(none)'
            : implode(', ', array_slice($topics, 0, 5));

        return <<<PROMPT
Pick the ONE draft from the list below that the writer would most want to return to today, given the recent topics on their site. Reply with JSON only, no preamble: {"id": <int>, "nudge": "<one short sentence, max 18 words, evocative, no productivity language>"}.

Recent site topics: {$topicBlock}

Drafts:
{$candidateBlock}
PROMPT;
    }

    private function trimExcerpt(string $text): string
    {
        $text = trim((string) preg_replace('/\s+/', ' ', $text));
        if ($text === '') {
            return '(no excerpt)';
        }
        if (function_exists('mb_strimwidth')) {
            return mb_strimwidth($text, 0, self::EXCERPT_CHARS, '…');
        }
        return strlen($text) > self::EXCERPT_CHARS ? substr($text, 0, self::EXCERPT_CHARS - 1) . '…' : $text;
    }

    /** @return array{post_id:int,nudge:string}|null */
    private function parse(string $text): ?array
    {
        $text = trim($text);
        if ($text === '') {
            return null;
        }
        // Extract first {...} block in case the model wraps it in prose.
        if (preg_match('/\{.*\}/s', $text, $m)) {
            $text = $m[0];
        }
        $data = json_decode($text, true);
        if (! is_array($data)) {
            return null;
        }
        $id = isset($data['id']) ? (int) $data['id'] : 0;
        $nudge = isset($data['nudge']) ? trim((string) $data['nudge']) : '';
        if ($id <= 0) {
            return null;
        }
        return ['post_id' => $id, 'nudge' => $nudge];
    }
}
