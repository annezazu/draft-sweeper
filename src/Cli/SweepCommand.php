<?php
declare(strict_types=1);

namespace DraftSweeper\Cli;

use DraftSweeper\Plugin;

/**
 * Draft Sweeper CLI commands.
 */
final class SweepCommand
{
    public function __construct(private readonly Plugin $plugin)
    {
    }

    /**
     * Show top scored drafts.
     *
     * ## OPTIONS
     *
     * [--user=<id>]
     * : Limit to a specific author's drafts. Default: all users.
     *
     * [--limit=<n>]
     * : Number of rows to show. Default: 5.
     *
     * ## EXAMPLES
     *
     *     wp draft-sweeper top --limit=10
     */
    public function top($args, $assoc): void
    {
        $userId = isset($assoc['user']) ? (int) $assoc['user'] : null;
        $limit = isset($assoc['limit']) ? max(1, (int) $assoc['limit']) : 5;

        $drafts = $this->plugin->repository()->recent($userId);
        if ($drafts === []) {
            \WP_CLI::log('No drafts found.');
            return;
        }

        $calc = $this->plugin->calculator();
        $topics = $this->plugin->topicsProvider()->recentTermFrequencies();
        $summarizer = $this->plugin->summaryGenerator();

        $rows = [];
        foreach ($drafts as $draft) {
            $score = $calc->calculate($draft, $topics);
            $rows[] = [
                'id' => $draft->id,
                'title' => mb_strimwidth($draft->title, 0, 50, '…'),
                'age_days' => $draft->daysSinceModified,
                'words' => $draft->wordCount,
                'C' => round($score->completeness, 2),
                'R' => round($score->recency, 2),
                'T' => round($score->relevance, 2),
                'total' => round($score->total, 3),
                'summary' => mb_strimwidth($summarizer->summarize($draft), 0, 80, '…'),
            ];
        }
        usort($rows, fn($a, $b) => $b['total'] <=> $a['total']);
        $rows = array_slice($rows, 0, $limit);

        \WP_CLI\Utils\format_items('table', $rows, array_keys($rows[0]));
    }
}
