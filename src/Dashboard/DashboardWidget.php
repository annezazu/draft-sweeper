<?php
declare(strict_types=1);

namespace DraftSweeper\Dashboard;

use DraftSweeper\Drafts\DraftSnapshot;
use DraftSweeper\Plugin;

final class DashboardWidget
{
    private const WIDGET_ID = 'draft_sweeper_widget';
    private const NONCE_ACTION = 'draft_sweeper_widget';
    private const DISMISSED_META = '_draft_sweeper_dismissed';
    private const DISMISS_TTL_DAYS = 14;
    private const TOGGLE_CAP = 'manage_options';

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
        $url = plugin_dir_url($this->plugin->pluginFile());
        $dir = plugin_dir_path($this->plugin->pluginFile());
        wp_enqueue_style('draft-sweeper-widget', $url . 'assets/widget.css', [], (string) filemtime($dir . 'assets/widget.css'));
        wp_enqueue_script('draft-sweeper-widget', $url . 'assets/widget.js', ['jquery'], (string) filemtime($dir . 'assets/widget.js'), true);
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

    public function ajaxToggleAi(): void
    {
        check_ajax_referer(self::NONCE_ACTION, 'nonce');
        if (! current_user_can(self::TOGGLE_CAP)) {
            wp_send_json_error('forbidden', 403);
        }
        $on = isset($_POST['enabled']) && (string) $_POST['enabled'] === '1';
        $settings = get_option('draft_sweeper_settings', []);
        if (! is_array($settings)) {
            $settings = [];
        }
        $settings['enable_ai'] = $on;
        update_option('draft_sweeper_settings', $settings);

        wp_send_json_success([
            'enabled' => $on,
            'html'    => $this->renderBody(),
        ]);
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

    /**
     * Stable day index in the site's timezone. Increments at site-local midnight.
     */
    private function dayIndex(): int
    {
        return ((int) wp_date('Y')) * 366 + ((int) wp_date('z'));
    }

    private function renderShell(): string
    {
        ob_start();
        ?>
        <div class="ds-widget">
            <?php $this->renderAiToggle(); ?>
            <div class="ds-body-wrap"><?php echo $this->renderBody(); ?></div>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    private function renderBody(): string
    {
        $userId = $this->plugin->settings()['scope'] === 'mine' ? get_current_user_id() : null;
        $allDrafts = $this->plugin->repository()->recent($userId);
        $dismissed = $this->activeDismissals();
        $available = array_values(array_filter($allDrafts, fn($d) => ! isset($dismissed[$d->id])));

        if ($available === []) {
            return '<p class="ds-empty">'
                . esc_html__('Nothing hiding in your draft pile. Nice work.', 'draft-sweeper')
                . '</p>';
        }

        $calc = $this->plugin->calculator();
        $topics = $this->plugin->topicsProvider()->recentTermFrequencies();

        $scored = [];
        foreach ($available as $draft) {
            $scored[] = ['draft' => $draft, 'score' => $calc->calculate($draft, $topics)];
        }

        $nudge = '';
        $aiPicker = $this->plugin->aiDailyPicker();
        if ($aiPicker !== null) {
            $picked = $aiPicker->pick($scored, $this->topicLabels($topics));
            if ($picked !== null) {
                $todays = ['draft' => $picked['draft'], 'score' => $picked['score']];
                $nudge = $picked['nudge'];
            } else {
                $todays = null;
            }
        } else {
            $todays = $this->plugin->dailyPicker()->pick($scored);
        }

        if ($todays === null) {
            return $this->renderEmpty();
        }

        $summarizer = $this->plugin->summaryGenerator();

        ob_start();
        ?>
        <ul class="ds-list">
            <?php $this->renderItem($todays['draft'], $summarizer, $nudge); ?>
        </ul>
        <?php
        return (string) ob_get_clean();
    }

    private function renderEmpty(): string
    {
        return '<p class="ds-empty">'
            . esc_html__('Nothing hiding in your draft pile. Nice work.', 'draft-sweeper')
            . '</p>';
    }

    /**
     * @param array<int, int> $topics term ID => frequency
     * @return list<string>
     */
    private function topicLabels(array $topics): array
    {
        if ($topics === []) {
            return [];
        }
        arsort($topics);
        $labels = [];
        foreach (array_slice(array_keys($topics), 0, 5, true) as $termId) {
            $term = function_exists('get_term') ? get_term((int) $termId) : null;
            if ($term && ! is_wp_error($term) && isset($term->name) && $term->name !== '') {
                $labels[] = (string) $term->name;
            }
        }
        return $labels;
    }

    /**
     * "Enhance with AI" toggle — only rendered when an `ai_provider`
     * connector is registered via the WP 7.0 Connectors API. The
     * `?draft_sweeper_force_toggle=1` query flag is a design-review
     * escape hatch (admin-only, no state change) that lets the
     * Playground preview show the control without a real provider.
     */
    private function renderAiToggle(): void
    {
        if (! current_user_can(self::TOGGLE_CAP)) {
            return;
        }
        if (! self::aiProviderRegistered() && ! self::forceTogglePreview()) {
            return;
        }

        $on = (bool) ($this->plugin->settings()['enable_ai'] ?? true);
        $classes = ['components-form-toggle', 'ds-ai-toggle__form-toggle'];
        if ($on) {
            $classes[] = 'is-checked';
        }
        ?>
        <div class="ds-ai-toggle">
            <button
                type="button"
                class="<?php echo esc_attr(implode(' ', $classes)); ?>"
                role="switch"
                aria-checked="<?php echo $on ? 'true' : 'false'; ?>"
                aria-labelledby="ds-ai-toggle-label"
                aria-describedby="ds-ai-toggle-help"
            >
                <span class="components-form-toggle__track" aria-hidden="true"></span>
                <span class="components-form-toggle__thumb" aria-hidden="true"></span>
                <span class="ds-ai-toggle__spinner" aria-hidden="true"></span>
            </button>
            <label
                id="ds-ai-toggle-label"
                class="ds-ai-toggle__label"
            ><?php esc_html_e('Uses AI', 'draft-sweeper'); ?></label>
            <span
                id="ds-ai-toggle-help"
                class="ds-ai-toggle__tooltip"
                role="tooltip"
                data-on="<?php esc_attr_e('AI picks today\'s draft and writes a nudge.', 'draft-sweeper'); ?>"
                data-off="<?php esc_attr_e('Draft shows first few words.', 'draft-sweeper'); ?>"
            ><?php
                echo esc_html(
                    $on
                        ? __('AI picks today\'s draft and writes a nudge.', 'draft-sweeper')
                        : __('Draft shows first few words.', 'draft-sweeper')
                );
            ?></span>
        </div>
        <?php
    }

    private static function aiProviderRegistered(): bool
    {
        if (! function_exists('wp_get_connectors')) {
            return false;
        }
        foreach ((array) wp_get_connectors() as $connector) {
            if (($connector['type'] ?? '') === 'ai_provider') {
                return true;
            }
        }
        return false;
    }

    private static function forceTogglePreview(): bool
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended — read-only UI flag, admin-gated above.
        return ! empty($_GET['draft_sweeper_force_toggle']);
    }

    private function renderItem(DraftSnapshot $draft, $summarizer, string $nudge = ''): void
    {
        $teaser = $nudge !== '' ? $nudge : $this->teaser($draft, $summarizer);
        $prompt = sprintf(
            /* translators: %s is a relative time phrase like "two weeks ago" or "in October". */
            __('Pick up where you left off %s', 'draft-sweeper'),
            $this->timePhrase($draft)
        );
        ?>
        <li class="ds-item" data-id="<?php echo esc_attr((string) $draft->id); ?>">
            <span class="ds-badge"><?php echo esc_html($prompt); ?></span>
            <?php if ($teaser !== '') : ?>
                <p class="ds-teaser<?php echo $nudge !== '' ? ' ds-teaser-ai' : ''; ?>"><?php echo esc_html($teaser); ?></p>
            <?php endif; ?>
            <a class="ds-title" href="<?php echo esc_url($draft->editLink); ?>">
                <?php echo wp_kses($this->displayTitle($draft), ['span' => ['class' => true]]); ?>
            </a>
            <div class="ds-actions">
                <a class="button button-primary" href="<?php echo esc_url($draft->editLink); ?>">
                    <?php esc_html_e('Pick this up', 'draft-sweeper'); ?>
                </a>
                <button type="button" class="ds-dismiss">
                    <?php esc_html_e('Not today', 'draft-sweeper'); ?>
                </button>
            </div>
        </li>
        <?php
    }

    /**
     * Adapts the EvocativeDate phrasing so it reads naturally after
     * "Pick up where you left off ...". Most evocative phrases start with
     * "from", which doesn't sit right after "left off".
     */
    private function timePhrase(DraftSnapshot $draft): string
    {
        if ($draft->evocativeStarted === '') {
            return $draft->startedHuman . ' ago';
        }
        $phrase = $draft->evocativeStarted;
        if (str_starts_with($phrase, 'from a ') && str_contains($phrase, ' in ')) {
            return 'on a ' . substr($phrase, 7);
        }
        if (str_starts_with($phrase, 'from last ')) {
            return substr($phrase, 5);
        }
        if (str_starts_with($phrase, 'from ')) {
            return 'in ' . substr($phrase, 5);
        }
        return $phrase;
    }

    /**
     * Picks the most evocative teaser line: the draft's own opening sentence
     * if we have one, otherwise the AI/template summary.
     */
    private function teaser(DraftSnapshot $draft, $summarizer): string
    {
        if ($draft->openingSentence !== '') {
            return $draft->openingSentence;
        }
        return $summarizer->summarize($draft);
    }

    private function displayTitle(DraftSnapshot $draft): string
    {
        if ($draft->hasTitle) {
            return esc_html($draft->title);
        }

        return '<span class="ds-untitled">' . esc_html__('Untitled post', 'draft-sweeper') . '</span>';
    }
}
