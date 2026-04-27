<?php
declare(strict_types=1);

namespace DraftSweeper\Drafts;

/**
 * Per-user storage of "today's draft" so the widget shows the same pick
 * all day instead of reshuffling on every dashboard load. Rolls over at
 * local midnight (site timezone).
 *
 * Shape persisted in user meta:
 *   [
 *     'date'        => 'YYYY-MM-DD',
 *     'post_id'     => int,
 *     'repick_used' => bool,    // user already used their one re-pick today
 *     'exhausted'   => bool,    // user dismissed the re-pick too — done for the day
 *     'nudge'       => string,  // optional richer reason from AI picker
 *   ]
 */
final class DailyPickStore
{
    public const META_KEY = '_draft_sweeper_daily_pick';

    public function today(): string
    {
        if (function_exists('wp_date')) {
            return wp_date('Y-m-d');
        }
        return date('Y-m-d');
    }

    /** @return array{date:string,post_id:int,repick_used:bool,exhausted:bool,nudge:string}|null */
    public function get(int $userId): ?array
    {
        $raw = get_user_meta($userId, self::META_KEY, true);
        if (! is_array($raw)) {
            return null;
        }
        if (($raw['date'] ?? '') !== $this->today()) {
            return null;
        }
        return [
            'date'        => (string) $raw['date'],
            'post_id'     => (int) ($raw['post_id'] ?? 0),
            'repick_used' => (bool) ($raw['repick_used'] ?? false),
            'exhausted'   => (bool) ($raw['exhausted'] ?? false),
            'nudge'       => (string) ($raw['nudge'] ?? ''),
        ];
    }

    public function set(int $userId, int $postId, string $nudge = ''): void
    {
        update_user_meta($userId, self::META_KEY, [
            'date'        => $this->today(),
            'post_id'     => $postId,
            'repick_used' => false,
            'exhausted'   => false,
            'nudge'       => $nudge,
        ]);
    }

    public function replacePick(int $userId, int $postId, string $nudge = ''): void
    {
        update_user_meta($userId, self::META_KEY, [
            'date'        => $this->today(),
            'post_id'     => $postId,
            'repick_used' => true,
            'exhausted'   => false,
            'nudge'       => $nudge,
        ]);
    }

    public function markExhausted(int $userId): void
    {
        $existing = $this->get($userId) ?? [
            'date'        => $this->today(),
            'post_id'     => 0,
            'repick_used' => true,
            'exhausted'   => false,
            'nudge'       => '',
        ];
        $existing['exhausted'] = true;
        update_user_meta($userId, self::META_KEY, $existing);
    }
}
