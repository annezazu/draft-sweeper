<?php
declare(strict_types=1);

namespace DraftSweeper\Dashboard;

use DraftSweeper\Drafts\DraftSnapshot;
use DraftSweeper\Plugin;
use DraftSweeper\Scoring\Score;

final class DashboardWidget
{
    private const WIDGET_ID = 'draft_sweeper_widget';
    private const NONCE_ACTION = 'draft_sweeper_widget';
    private const DISMISSED_META = '_draft_sweeper_dismissed';
    private const DISMISS_TTL_DAYS = 14;
    private const TOP_N = 5;

    public function __construct(private readonly Plugin $plugin)
    {
    }

    public function register(): void
    {
        if (! current_user_can('edit_posts')) {
            return;
        }
        wp_add_dashboard_widget(
            self::WIDGET_ID,
            __('Draft Sweeper', 'draft-sweeper'),
            [$this, 'render']
        );
    }

    public function enqueueAssets(string $hook): void
    {
        if ($hook !== 'index.php') {
            return;
        }
        $url = $this->plugin->pluginUrl();
        wp_enqueue_style('draft-sweeper-widget', $url . 'assets/widget.css', [], '0.1.0');
        wp_enqueue_script('draft-sweeper-widget', $url . 'assets/widget.js', ['jquery'], '0.1.0', true);
        wp_localize_script('draft-sweeper-widget', 'DraftSweeper', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce(self::NONCE_ACTION),
        ]);
    }

    public function render(): void
    {
        echo $this->renderListMarkup();
    }

    public function ajaxRefresh(): void
    {
        check_ajax_referer(self::NONCE_ACTION, 'nonce');
        if (! current_user_can('edit_posts')) {
            wp_send_json_error('forbidden', 403);
        }
        wp_send_json_success(['html' => $this->renderListMarkup()]);
    }

    public function ajaxDismiss(): void
    {
        check_ajax_referer(self::NONCE_ACTION, 'nonce');
        if (! current_user_can('edit_posts')) {
            wp_send_json_error('forbidden', 403);
        }
        $postId = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
        if ($postId <= 0) {
            wp_send_json_error('bad_request', 400);
        }
        $this->dismiss($postId);
        wp_send_json_success();
    }

    private function dismiss(int $postId): void
    {
        $userId = get_current_user_id();
        $dismissed = get_user_meta($userId, self::DISMISSED_META, true);
        if (! is_array($dismissed)) {
            $dismissed = [];
        }
        $dismissed[$postId] = time();
        update_user_meta($userId, self::DISMISSED_META, $dismissed);
    }

    /** @return array<int, int> */
    private function activeDismissals(): array
    {
        $userId = get_current_user_id();
        $dismissed = get_user_meta($userId, self::DISMISSED_META, true);
        if (! is_array($dismissed)) {
            return [];
        }
        $cutoff = time() - (self::DISMISS_TTL_DAYS * DAY_IN_SECONDS);
        return array_filter($dismissed, static fn($ts) => (int) $ts >= $cutoff);
    }

    private function renderListMarkup(): string
    {
        $userId = $this->plugin->settings()['scope'] === 'mine' ? get_current_user_id() : null;
        $drafts = $this->plugin->repository()->recent($userId);
        $dismissed = $this->activeDismissals();
        $drafts = array_values(array_filter($drafts, fn($d) => ! isset($dismissed[$d->id])));

        if ($drafts === []) {
            return '<p class="ds-empty">' . esc_html__('No abandoned drafts to surface. Nice work.', 'draft-sweeper') . '</p>';
        }

        $calc = $this->plugin->calculator();
        $topics = $this->plugin->topicsProvider()->recentTermFrequencies();
        $nudger = $this->plugin->nudgeGenerator();

        $scored = [];
        foreach ($drafts as $draft) {
            $score = $calc->calculate($draft, $topics);
            $scored[] = ['draft' => $draft, 'score' => $score];
        }
        usort($scored, fn($a, $b) => $b['score']->total <=> $a['score']->total);
        $top = array_slice($scored, 0, self::TOP_N);

        ob_start();
        echo '<ul class="ds-list">';
        foreach ($top as $row) {
            /** @var DraftSnapshot $draft */
            $draft = $row['draft'];
            /** @var Score $score */
            $score = $row['score'];
            $nudge = $nudger->generate($draft, $score);
            $pct = (int) round($score->total * 100);
            ?>
            <li class="ds-item" data-id="<?php echo esc_attr((string) $draft->id); ?>">
                <div class="ds-item-head">
                    <a class="ds-title" href="<?php echo esc_url($draft->editLink); ?>">
                        <?php echo esc_html($draft->title); ?>
                    </a>
                    <span class="ds-score" title="<?php echo esc_attr(sprintf(
                        'C %d / R %d / T %d',
                        (int) round($score->completeness * 100),
                        (int) round($score->recency * 100),
                        (int) round($score->relevance * 100)
                    )); ?>"><?php echo esc_html((string) $pct); ?></span>
                </div>
                <p class="ds-nudge"><?php echo esc_html($nudge); ?></p>
                <div class="ds-actions">
                    <a class="button button-primary button-small" href="<?php echo esc_url($draft->editLink); ?>">
                        <?php esc_html_e('Open', 'draft-sweeper'); ?>
                    </a>
                    <button type="button" class="button button-small ds-dismiss">
                        <?php esc_html_e('Not now', 'draft-sweeper'); ?>
                    </button>
                </div>
            </li>
            <?php
        }
        echo '</ul>';
        return (string) ob_get_clean();
    }
}
