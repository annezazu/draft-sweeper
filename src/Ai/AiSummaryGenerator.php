<?php
declare(strict_types=1);

namespace DraftSweeper\Ai;

use DraftSweeper\Drafts\DraftSnapshot;

/**
 * Uses the WP 7.0 AI Connector (via AiClient) to produce a one-line topic
 * summary of a draft. Falls back to ExcerptSummaryGenerator on any failure
 * or when no provider is configured.
 *
 * Summaries are cached per-post in post meta keyed by content hash so we
 * don't re-spend tokens on every dashboard load.
 */
final class AiSummaryGenerator implements SummaryGenerator
{
    private const META_KEY = '_draft_sweeper_summary';

    public function __construct(
        private readonly AiProviderResolver $resolver,
        private readonly ExcerptSummaryGenerator $fallback,
    ) {
    }

    public function summarize(DraftSnapshot $draft): string
    {
        $provider = $this->resolver->resolve();
        if ($provider === null || ! class_exists('\\WordPress\\AiClient\\AiClient')) {
            return $this->fallback->summarize($draft);
        }

        $cacheKey = $this->cacheKey($draft);
        $cached = get_post_meta($draft->id, self::META_KEY, true);
        if (is_array($cached) && ($cached['key'] ?? '') === $cacheKey && is_string($cached['text'] ?? null)) {
            return $cached['text'];
        }

        $prompt = <<<PROMPT
Summarize this in-progress blog draft in ONE plain sentence (max 25 words) describing what it's about. No quotes, no preamble, no emoji.

Title: {$draft->title}
Excerpt: {$draft->excerpt}
PROMPT;

        try {
            $response = \WordPress\AiClient\AiClient::generateText([
                'provider'   => $provider['id'],
                'prompt'     => $prompt,
                'max_tokens' => 80,
            ]);
            $text = is_string($response) ? trim($response) : trim((string) ($response['text'] ?? ''));
            if ($text === '') {
                return $this->fallback->summarize($draft);
            }
            update_post_meta($draft->id, self::META_KEY, ['key' => $cacheKey, 'text' => $text]);
            return $text;
        } catch (\Throwable) {
            return $this->fallback->summarize($draft);
        }
    }

    private function cacheKey(DraftSnapshot $d): string
    {
        return md5($d->title . '|' . $d->excerpt . '|' . $d->wordCount);
    }
}
