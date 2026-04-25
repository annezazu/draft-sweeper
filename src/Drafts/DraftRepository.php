<?php
declare(strict_types=1);

namespace DraftSweeper\Drafts;

final class DraftRepository
{
    public function __construct(
        private readonly int $limit = 50,
    ) {
    }

    /**
     * @return DraftSnapshot[]
     */
    public function recent(?int $userId = null): array
    {
        $args = [
            'post_type'      => 'post',
            'post_status'    => 'draft',
            'orderby'        => 'modified',
            'order'          => 'DESC',
            'posts_per_page' => $this->limit,
            'no_found_rows'  => true,
        ];
        if ($userId !== null) {
            $args['author'] = $userId;
        }

        $query = new \WP_Query($args);
        $now = time();
        $out = [];

        foreach ($query->posts as $post) {
            $modifiedTs = (int) get_post_modified_time('U', true, $post);
            $createdTs = (int) get_post_time('U', true, $post);
            $days = max(0, (int) floor(($now - $modifiedTs) / DAY_IN_SECONDS));

            $categories = wp_get_post_categories($post->ID, ['fields' => 'ids']);
            $tags = wp_get_post_tags($post->ID, ['fields' => 'ids']);

            $title = $post->post_title;
            $content = strip_shortcodes(wp_strip_all_tags($post->post_content));

            $out[] = new DraftSnapshot(
                id: $post->ID,
                title: $title !== '' ? $title : __('(no title)', 'draft-sweeper'),
                editLink: (string) get_edit_post_link($post->ID, 'raw'),
                wordCount: str_word_count($content),
                hasTitle: trim($title) !== '',
                hasExcerpt: trim((string) $post->post_excerpt) !== '',
                hasFeaturedImage: (bool) get_post_thumbnail_id($post->ID),
                categoryCount: count($categories),
                tagCount: count($tags),
                termIds: array_map('intval', array_merge($categories, $tags)),
                daysSinceModified: $days,
                modifiedHuman: human_time_diff($modifiedTs, $now),
                startedHuman: human_time_diff($createdTs, $now),
                excerpt: wp_trim_words($content, 30),
            );
        }

        return $out;
    }
}
