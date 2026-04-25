<?php
/**
 * Seed sample drafts for local testing.
 * Usage: wp eval-file scripts/seed-drafts.php
 */
global $wpdb;

$now = time();
$samples = [
    ['Half-finished essay on remote work', 600, 90],
    ['Quick thought from last week', 80, 5],
    ['Long-abandoned tutorial', 1200, 540],
    ['', 30, 2], // intentionally untitled
    ['Almost-ready announcement', 700, 30],
    ['Old draft from years ago', 200, 900],
];

foreach ($samples as [$title, $words, $age]) {
    $sentences = [
        'Lorem ipsum dolor sit amet, consectetur adipiscing elit.',
        'Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.',
        'Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris.',
    ];
    $content = '';
    while (str_word_count($content) < $words) {
        $content .= $sentences[array_rand($sentences)] . ' ';
    }

    $id = wp_insert_post([
        'post_title'   => $title,
        'post_content' => trim($content),
        'post_status'  => 'draft',
        'post_author'  => 1,
    ]);

    // wp_update_post would clobber post_modified. Update directly.
    $when = gmdate('Y-m-d H:i:s', $now - $age * DAY_IN_SECONDS);
    $whenLocal = get_date_from_gmt($when);
    $wpdb->update(
        $wpdb->posts,
        [
            'post_date'         => $whenLocal,
            'post_date_gmt'     => $when,
            'post_modified'     => $whenLocal,
            'post_modified_gmt' => $when,
        ],
        ['ID' => $id]
    );
    clean_post_cache($id);

    WP_CLI::log("Seeded #{$id}: " . ($title ?: '(no title)') . " · {$age}d old · ~{$words} words");
}
