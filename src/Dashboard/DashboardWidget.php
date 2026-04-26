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
    private const OFFSET_META = '_draft_sweeper_offset';
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
        wp_enqueue_style('draft-sweeper-widget', $url . 'assets/widget.css', ['dashicons'], '0.3.0');
        wp_enqueue_script('draft-sweeper-widget', $url . 'assets/widget.js', ['jquery'], '0.3.0', true);
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
        $this->bumpOffset();
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

    private function offset(): int
    {
        $offset = (int) get_user_meta(get_current_user_id(), self::OFFSET_META, true);
        return max(0, $offset);
    }

    private function bumpOffset(): void
    {
        update_user_meta(get_current_user_id(), self::OFFSET_META, $this->offset() + self::TOP_N);
    }

    private function resetOffset(): void
    {
        delete_user_meta(get_current_user_id(), self::OFFSET_META);
    }

    private function renderShell(): string
    {
        $userId = $this->plugin->settings()['scope'] === 'mine' ? get_current_user_id() : null;
        $allDrafts = $this->plugin->repository()->recent($userId);
        $dismissed = $this->activeDismissals();
        $available = array_values(array_filter($allDrafts, fn($d) => ! isset($dismissed[$d->id])));

        if ($available === []) {
            return $this->renderEmpty();
        }

        $calc = $this->plugin->calculator();
        $topics = $this->plugin->topicsProvider()->recentTermFrequencies();

        $scored = [];
        foreach ($available as $draft) {
            $scored[] = ['draft' => $draft, 'score' => $calc->calculate($draft, $topics)];
        }
        usort($scored, fn($a, $b) => $b['score']->total <=> $a['score']->total);

        $total = count($scored);
        $offset = $this->offset();
        if ($offset >= $total) {
            $this->resetOffset();
            $offset = 0;
        }
        $window = array_slice($scored, $offset, self::TOP_N);
        if ($window === []) {
            $this->resetOffset();
            $window = array_slice($scored, 0, self::TOP_N);
            $offset = 0;
        }
        $hasMore = $total > self::TOP_N;

        $highlight = new Highlight();
        $summarizer = $this->plugin->summaryGenerator();

        ob_start();
        ?>
        <div class="ds-widget">
            <div class="ds-toolbar">
                <p class="ds-intro">
                    <?php
                    printf(
                        /* translators: 1: index of first surfaced draft, 2: index of last, 3: total */
                        esc_html__('Showing %1$d–%2$d of %3$d unfinished drafts.', 'draft-sweeper'),
                        $offset + 1,
                        min($offset + self::TOP_N, $total),
                        $total
                    );
                    ?>
                </p>
            </div>
            <ul class="ds-list">
                <?php foreach ($window as $row) {
                    $this->renderItem($row['draft'], $row['score'], $highlight, $summarizer);
                } ?>
            </ul>
            <?php if ($hasMore) : ?>
                <div class="ds-footer">
                    <button type="button" class="button ds-refresh">
                        <span class="dashicons dashicons-update-alt" aria-hidden="true"></span>
                        <span class="ds-refresh-label"><?php esc_html_e('Show me more drafts', 'draft-sweeper'); ?></span>
                    </button>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    private function renderEmpty(): string
    {
        return '<div class="ds-widget"><p class="ds-empty">'
            . esc_html__('No abandoned drafts to surface. Nice work.', 'draft-sweeper')
            . '</p></div>';
    }

    private function renderItem(DraftSnapshot $draft, Score $score, Highlight $highlight, $summarizer): void
    {
        $reason = $highlight->reason($draft, $score);
        $minutes = $highlight->minutesToFinish($draft);
        $summary = $summarizer->summarize($draft);
        $displayTitle = $this->displayTitle($draft);
        $completenessPct = (int) round($score->completeness * 100);
        ?>
        <li class="ds-item" data-id="<?php echo esc_attr((string) $draft->id); ?>">
            <span class="ds-badge ds-badge--<?php echo esc_attr(str_replace('_', '-', $reason)); ?>">
                <?php echo esc_html($this->reasonLabel($reason)); ?>
            </span>
            <a class="ds-title" href="<?php echo esc_url($draft->editLink); ?>">
                <?php echo wp_kses($displayTitle, ['span' => ['class' => true]]); ?>
            </a>
            <p class="ds-meta">
                <span><?php echo esc_html(sprintf(
                    /* translators: %s: human time diff like "6 months" */
                    __('Started %s ago', 'draft-sweeper'),
                    $draft->startedHuman
                )); ?></span>
                <span class="ds-meta-sep" aria-hidden="true">·</span>
                <span><?php
                    printf(
                        esc_html(_n('%s word', '%s words', $draft->wordCount, 'draft-sweeper')),
                        esc_html(number_format_i18n($draft->wordCount))
                    );
                ?></span>
                <?php if ($minutes !== null) : ?>
                    <span class="ds-meta-sep" aria-hidden="true">·</span>
                    <span><?php
                        printf(
                            esc_html(_n('~%d min to finish', '~%d min to finish', $minutes, 'draft-sweeper')),
                            $minutes
                        );
                    ?></span>
                <?php endif; ?>
            </p>
            <div class="ds-progress" role="progressbar" aria-valuenow="<?php echo esc_attr((string) $completenessPct); ?>" aria-valuemin="0" aria-valuemax="100" aria-label="<?php esc_attr_e('Completeness', 'draft-sweeper'); ?>">
                <div class="ds-progress-bar" style="width: <?php echo esc_attr((string) $completenessPct); ?>%"></div>
            </div>
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

    private function reasonLabel(string $reason): string
    {
        return match ($reason) {
            Highlight::REASON_ALMOST_DONE  => __('Almost done', 'draft-sweeper'),
            Highlight::REASON_ON_TREND     => __('On a topic your readers love', 'draft-sweeper'),
            Highlight::REASON_BURIED       => __('Buried treasure', 'draft-sweeper'),
            Highlight::REASON_HALF_WRITTEN => __('Halfway there', 'draft-sweeper'),
            default                        => __('Worth a fresh look', 'draft-sweeper'),
        };
    }

    private function displayTitle(DraftSnapshot $draft): string
    {
        if ($draft->hasTitle) {
            return esc_html($draft->title);
        }

        $excerpt = trim($draft->excerpt);
        $snippet = $excerpt !== '' ? mb_strimwidth($excerpt, 0, 50, '…') : '';

        $label = '<span class="ds-untitled">' . esc_html__('(no title)', 'draft-sweeper') . '</span>';
        if ($snippet !== '') {
            $label .= ' ' . esc_html($snippet);
        }
        return $label;
    }
}
