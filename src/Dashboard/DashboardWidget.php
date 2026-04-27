<?php
declare(strict_types=1);

namespace DraftSweeper\Dashboard;

use DraftSweeper\Drafts\DailyPickStore;
use DraftSweeper\Drafts\DraftSnapshot;
use DraftSweeper\Plugin;
use DraftSweeper\Scoring\Score;

/**
 * Surfaces a single, curated draft per user per day. The selection is
 * cached in user meta and only changes when the date rolls over (site
 * timezone) or when the user uses their one allowed "Save for later"
 * re-pick. After the re-pick is also dismissed, the widget says
 * "see you tomorrow" until midnight.
 */
final class DashboardWidget
{
    private const WIDGET_ID = 'draft_sweeper_widget';
    private const NONCE_ACTION = 'draft_sweeper_widget';
    private const DISMISSED_META = '_draft_sweeper_dismissed';
    private const DISMISS_TTL_DAYS = 14;
    private const AI_CANDIDATE_TOPICS = 5;

    public function __construct(
        private readonly Plugin $plugin,
        private readonly DailyPickStore $store,
    ) {
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
        wp_enqueue_style('draft-sweeper-widget', $url . 'assets/widget.css', ['dashicons'], '0.5.0');
        wp_enqueue_script('draft-sweeper-widget', $url . 'assets/widget.js', ['jquery'], '0.5.0', true);
        wp_localize_script('draft-sweeper-widget', 'DraftSweeper', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce(self::NONCE_ACTION),
        ]);
    }

    public function render(): void
    {
        echo $this->renderShell();
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

        $userId = get_current_user_id();
        $state = $this->store->get($userId);

        // If they dismissed today's pick and haven't used the re-pick, try again.
        // Otherwise, they're done for the day.
        if ($state !== null && $state['post_id'] === $postId && ! $state['repick_used']) {
            $candidates = $this->candidates();
            if ($candidates === []) {
                $this->store->markExhausted($userId);
            } else {
                $picked = $this->pickFor($candidates);
                if ($picked === null) {
                    $this->store->markExhausted($userId);
                } else {
                    $this->store->replacePick($userId, $picked['draft']->id, $picked['nudge']);
                }
            }
        } else {
            $this->store->markExhausted($userId);
        }

        wp_send_json_success(['html' => $this->renderShell()]);
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

    /** @return list<array{draft: DraftSnapshot, score: Score}> */
    private function candidates(): array
    {
        $userId = $this->plugin->settings()['scope'] === 'mine' ? get_current_user_id() : null;
        $allDrafts = $this->plugin->repository()->recent($userId);
        $dismissed = $this->activeDismissals();
        $available = array_values(array_filter($allDrafts, static fn($d) => ! isset($dismissed[$d->id])));
        if ($available === []) {
            return [];
        }

        $calc = $this->plugin->calculator();
        $topics = $this->plugin->topicsProvider()->recentTermFrequencies();

        $scored = [];
        foreach ($available as $draft) {
            $scored[] = ['draft' => $draft, 'score' => $calc->calculate($draft, $topics)];
        }
        return $scored;
    }

    /**
     * Resolves the pick for today, using the AI picker when configured and
     * falling back to deterministic top-1 otherwise. Returned shape always
     * includes a 'nudge' string (may be empty).
     *
     * @param list<array{draft: DraftSnapshot, score: Score}> $candidates
     * @return array{draft: DraftSnapshot, score: Score, nudge: string}|null
     */
    private function pickFor(array $candidates): ?array
    {
        $aiPicker = $this->plugin->aiDailyPicker();
        if ($aiPicker !== null) {
            $picked = $aiPicker->pick($candidates, $this->topTopicLabels());
            if ($picked !== null) {
                return $picked;
            }
        }
        $top = $this->plugin->dailyPicker()->pick($candidates);
        if ($top === null) {
            return null;
        }
        return ['draft' => $top['draft'], 'score' => $top['score'], 'nudge' => ''];
    }

    /** @return list<string> */
    private function topTopicLabels(): array
    {
        $freq = $this->plugin->topicsProvider()->recentTermFrequencies();
        if ($freq === []) {
            return [];
        }
        arsort($freq);
        $labels = [];
        foreach (array_slice(array_keys($freq), 0, self::AI_CANDIDATE_TOPICS, true) as $termId) {
            $term = get_term((int) $termId);
            if ($term && ! is_wp_error($term) && isset($term->name)) {
                $labels[] = (string) $term->name;
            }
        }
        return $labels;
    }

    private function renderShell(): string
    {
        $userId = get_current_user_id();
        $state = $this->store->get($userId);

        if ($state !== null && $state['exhausted']) {
            return $this->renderExhausted();
        }

        $candidates = $this->candidates();
        if ($candidates === []) {
            return $this->renderEmpty();
        }

        $pick = $this->resolvePick($state, $candidates);
        if ($pick === null) {
            return $this->renderEmpty();
        }

        return $this->renderHero($pick['draft'], $pick['score'], $pick['nudge'], count($candidates));
    }

    /**
     * @param array{date:string,post_id:int,repick_used:bool,exhausted:bool,nudge:string}|null $state
     * @param list<array{draft: DraftSnapshot, score: Score}> $candidates
     * @return array{draft: DraftSnapshot, score: Score, nudge: string}|null
     */
    private function resolvePick(?array $state, array $candidates): ?array
    {
        if ($state !== null) {
            foreach ($candidates as $row) {
                if ($row['draft']->id === $state['post_id']) {
                    return ['draft' => $row['draft'], 'score' => $row['score'], 'nudge' => $state['nudge']];
                }
            }
        }

        $picked = $this->pickFor($candidates);
        if ($picked === null) {
            return null;
        }
        $userId = get_current_user_id();
        if ($state === null) {
            $this->store->set($userId, $picked['draft']->id, $picked['nudge']);
        } else {
            // The previously-stored pick is gone (published, deleted, or dismissed
            // outside this flow). Refresh the pick without consuming the re-pick.
            $this->store->set($userId, $picked['draft']->id, $picked['nudge']);
        }
        return $picked;
    }

    private function renderHero(DraftSnapshot $draft, Score $score, string $nudge, int $totalPile): string
    {
        $highlight = new Highlight();
        $reason = $highlight->reason($draft, $score);
        $teaser = $this->teaser($draft, $nudge);
        $started = $draft->evocativeStarted !== '' ? $draft->evocativeStarted : ('from ' . $draft->startedHuman . ' ago');

        ob_start();
        ?>
        <div class="ds-widget ds-widget--hero">
            <div class="ds-hero" data-id="<?php echo esc_attr((string) $draft->id); ?>">
                <span class="ds-badge ds-badge--today">
                    <?php esc_html_e("Today's draft", 'draft-sweeper'); ?>
                </span>
                <span class="ds-badge ds-badge--reason">
                    <?php echo esc_html($this->reasonLabel($reason)); ?>
                </span>
                <?php if ($teaser !== '') : ?>
                    <p class="ds-teaser"><?php echo esc_html($teaser); ?></p>
                <?php endif; ?>
                <a class="ds-title" href="<?php echo esc_url($draft->editLink); ?>">
                    <?php echo wp_kses($this->displayTitle($draft), ['span' => ['class' => true]]); ?>
                </a>
                <p class="ds-meta">
                    <span><?php echo esc_html(ucfirst($started)); ?></span>
                    <span class="ds-meta-sep" aria-hidden="true">·</span>
                    <span><?php
                        printf(
                            esc_html(_n('%s word', '%s words', $draft->wordCount, 'draft-sweeper')),
                            esc_html(number_format_i18n($draft->wordCount))
                        );
                    ?></span>
                </p>
                <div class="ds-actions">
                    <a class="button button-primary" href="<?php echo esc_url($draft->editLink); ?>">
                        <?php esc_html_e('Pick this up', 'draft-sweeper'); ?>
                    </a>
                    <button type="button" class="button ds-dismiss">
                        <?php esc_html_e('Save for later', 'draft-sweeper'); ?>
                    </button>
                </div>
            </div>
            <?php if ($totalPile > 1) : ?>
                <p class="ds-pile-count"><?php
                    $other = $totalPile - 1;
                    printf(
                        esc_html(_n(
                            '%s other draft is hiding in your pile.',
                            '%s other drafts are hiding in your pile.',
                            $other,
                            'draft-sweeper'
                        )),
                        esc_html(number_format_i18n($other))
                    );
                ?></p>
            <?php endif; ?>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    private function renderEmpty(): string
    {
        return '<div class="ds-widget"><p class="ds-empty">'
            . esc_html__('Nothing hiding in your draft pile. Nice work.', 'draft-sweeper')
            . '</p></div>';
    }

    private function renderExhausted(): string
    {
        return '<div class="ds-widget"><p class="ds-empty">'
            . esc_html__('You\'ve cleared today. A fresh draft will surface tomorrow.', 'draft-sweeper')
            . '</p></div>';
    }

    private function teaser(DraftSnapshot $draft, string $nudge): string
    {
        if ($nudge !== '') {
            return $nudge;
        }
        if ($draft->openingSentence !== '') {
            return $draft->openingSentence;
        }
        return $this->plugin->summaryGenerator()->summarize($draft);
    }

    private function reasonLabel(string $reason): string
    {
        return match ($reason) {
            Highlight::REASON_ALMOST_DONE  => __('Nearly ready', 'draft-sweeper'),
            Highlight::REASON_ON_TREND     => __('Timely again', 'draft-sweeper'),
            Highlight::REASON_BURIED       => __('Buried treasure', 'draft-sweeper'),
            Highlight::REASON_HALF_WRITTEN => __('A spark in progress', 'draft-sweeper'),
            default                        => __('An idea waiting', 'draft-sweeper'),
        };
    }

    private function displayTitle(DraftSnapshot $draft): string
    {
        if ($draft->hasTitle) {
            return esc_html($draft->title);
        }

        $excerpt = trim($draft->excerpt);
        $snippet = $excerpt !== '' ? mb_strimwidth($excerpt, 0, 50, '…') : '';

        $label = '<span class="ds-untitled">' . esc_html__('Untitled', 'draft-sweeper') . '</span>';
        if ($snippet !== '') {
            $label .= ' · ' . esc_html($snippet);
        }
        return $label;
    }
}
