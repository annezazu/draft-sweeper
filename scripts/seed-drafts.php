<?php
/**
 * Seed sample drafts for local testing.
 * Usage: wp eval-file scripts/seed-drafts.php
 */
$now = time();
$samples = [
    ['Half-finished essay on remote work', 600, 90],
    ['Quick thought from last week', 80, 5],
    ['Long-abandoned tutorial', 1200, 540],
    ['New idea, barely started', 30, 2],
    ['Almost-ready announcement', 700, 30],
    ['Old draft from years ago', 200, 900],
];

foreach ($samples as [$title, $words, $age]) {
    $content = str_repeat('Lorem ipsum dolor sit amet. ', max(1, (int) ($words / 4)));
    $id = wp_insert_post([
        'post_title'   => $title,
        'post_content' => $content,
        'post_status'  => 'draft',
        'post_author'  => 1,
    ]);
    $when = date('Y-m-d H:i:s', $now - $age * DAY_IN_SECONDS);
    wp_update_post([
        'ID'                => $id,
        'post_date'         => $when,
        'post_date_gmt'     => $when,
        'post_modified'     => $when,
        'post_modified_gmt' => $when,
    ]);
    WP_CLI::log("Seeded draft #{$id}: {$title} ({$age} days old, ~{$words} words)");
}
