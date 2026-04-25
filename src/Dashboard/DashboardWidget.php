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
    private const TOP_N = 3;

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
        wp_enqueue_style('draft-sweeper-widget', $url . 'assets/widget.css', [], '0.2.0');
        wp_enqueue_script('draft-sweeper-widget', $url . 'assets/widget.js', ['jquery'], '0.2.0', true);
        wp_localize_script('draft-sweeper-widget', 'DraftSweeper', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce(self::NONCE_ACTION),
        ]);
    }

    public function render(): void
    {
        echo $this->renderShell();
    }

    public function ajaxRefresh(): void
    {
        check_ajax_referer(self::NONCE_ACTION, 'nonce');
        if (! current_user_can('edit_posts')) {
            wp_send_json_error('forbidden', 403);
        }
        wp_send_json_success(['html' => $this->renderShell()]);
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

    private function renderShell(): string
    {
        ob_start();
        ?>
        <div class="ds-widget">
            <div class="ds-toolbar">
                <p class="ds-intro"><?php esc_html_e('Drafts worth a second look.', 'draft-sweeper'); ?></p>
                <button type="button" class="button button-link ds-refresh" aria-label="<?php esc_attr_e('Refresh suggestions', 'draft-sweeper'); ?>">
                    <span class="dashicons dashicons-update" aria-hidden="true"></span>
                    <span class="screen-reader-text"><?php esc_html_e('Refresh', 'draft-sweeper'); ?></span>
                </button>
            </div>
            <div class="ds-list-wrap">
                <?php echo $this->renderListMarkup(); ?>
            </div>
        </div>
        <?php
        return (string) ob_get_clean();
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
        $summarizer = $this->plugin->summaryGenerator();

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
            $summary = $summarizer->summarize($draft);
            $pct = (int) round($score->total * 100);
            $displayTitle = $this->displayTitle($draft);
            ?>
            <li class="ds-item" data-id="<?php echo esc_attr((string) $draft->id); ?>">
                <div class="ds-item-head">
                    <a class="ds-title" href="<?php echo esc_url($draft->editLink); ?>">
                        <?php echo wp_kses(
                            $displayTitle['html'],
                            ['span' => ['class' => true]]
                        ); ?>
                    </a>
                    <span class="ds-score" title="<?php echo esc_attr(sprintf(
                        /* translators: %1$d completeness, %2$d recency, %3$d relevance */
                        __('Completeness %1$d%% · Recency %2$d%% · Relevance %3$d%%', 'draft-sweeper'),
                        (int) round($score->completeness * 100),
                        (int) round($score->recency * 100),
                        (int) round($score->relevance * 100)
                    )); ?>"><?php echo esc_html((string) $pct); ?></span>
                </div>
                <p class="ds-meta">
                    <span class="ds-meta-started"><?php echo esc_html($this->startedLabel($draft)); ?></span>
                    <span class="ds-meta-sep" aria-hidden="true">·</span>
                    <span class="ds-meta-words"><?php
                        printf(
                            esc_html(_n('%s word', '%s words', $draft->wordCount, 'draft-sweeper')),
                            esc_html(number_format_i18n($draft->wordCount))
                        );
                    ?></span>
                </p>
                <?php if ($summary !== '') : ?>
                    <p class="ds-summary"><?php echo esc_html($summary); ?></p>
                <?php endif; ?>
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

    /**
     * Builds the display title, falling back to "(no title) — first chars"
     * for untitled drafts. Returns HTML so we can style the fallback piece.
     *
     * @return array{html: string}
     */
    private function displayTitle(DraftSnapshot $draft): array
    {
        if ($draft->hasTitle) {
            return ['html' => esc_html($draft->title)];
        }

        $excerpt = trim($draft->excerpt);
        $snippet = $excerpt !== ''
            ? mb_strimwidth($excerpt, 0, 50, '…')
            : '';

        $label = '<span class="ds-untitled">' . esc_html__('(no title)', 'draft-sweeper') . '</span>';
        if ($snippet !== '') {
            $label .= ' ' . esc_html($snippet);
        }
        return ['html' => $label];
    }

    private function startedLabel(DraftSnapshot $draft): string
    {
        return sprintf(
            /* translators: %s: human time diff like "6 months" */
            __('Started %s ago', 'draft-sweeper'),
            $draft->startedHuman
        );
    }
}
