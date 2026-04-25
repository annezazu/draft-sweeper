<?php
declare(strict_types=1);

namespace DraftSweeper\Drafts;

final class RecentTopicsProvider
{
    private const CACHE_KEY = 'draft_sweeper_recent_topics';
    private const CACHE_TTL = HOUR_IN_SECONDS;

    public function __construct(
        private readonly int $windowDays = 180,
    ) {
    }

    /** @return array<int, int> term ID => frequency */
    public function recentTermFrequencies(): array
    {
        $cached = get_transient(self::CACHE_KEY);
        if (is_array($cached)) {
            return $cached;
        }

        $args = [
            'post_type'      => 'post',
            'post_status'    => 'publish',
            'date_query'     => [['after' => "-{$this->windowDays} days"]],
            'fields'         => 'ids',
            'posts_per_page' => 200,
            'no_found_rows'  => true,
        ];

        $query = new \WP_Query($args);
        $freq = [];
        foreach ($query->posts as $postId) {
            $cats = wp_get_post_categories($postId, ['fields' => 'ids']);
            $tags = wp_get_post_tags($postId, ['fields' => 'ids']);
            foreach (array_merge($cats, $tags) as $id) {
                $id = (int) $id;
                $freq[$id] = ($freq[$id] ?? 0) + 1;
            }
        }

        set_transient(self::CACHE_KEY, $freq, self::CACHE_TTL);
        return $freq;
    }

    public static function flush(): void
    {
        delete_transient(self::CACHE_KEY);
    }
}
