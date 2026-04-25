<?php
declare(strict_types=1);

namespace DraftSweeper\Settings;

final class SettingsPage
{
    private const OPTION = 'draft_sweeper_settings';
    private const SLUG = 'draft-sweeper';

    public function register(): void
    {
        register_setting('draft_sweeper', self::OPTION, [
            'type' => 'array',
            'sanitize_callback' => [$this, 'sanitize'],
            'default' => [
                'enable_ai' => true,
                'scope' => 'mine',
                'completeness' => 0.5,
                'recency' => 0.2,
                'relevance' => 0.3,
            ],
        ]);

        add_settings_section('ds_main', __('Draft Sweeper', 'draft-sweeper'), '__return_false', self::SLUG);

        add_settings_field('enable_ai', __('Use AI nudges', 'draft-sweeper'), [$this, 'fieldEnableAi'], self::SLUG, 'ds_main');
        add_settings_field('scope', __('Drafts to surface', 'draft-sweeper'), [$this, 'fieldScope'], self::SLUG, 'ds_main');
        add_settings_field('weights', __('Score weights', 'draft-sweeper'), [$this, 'fieldWeights'], self::SLUG, 'ds_main');
    }

    public function addMenu(): void
    {
        add_options_page(
            __('Draft Sweeper', 'draft-sweeper'),
            __('Draft Sweeper', 'draft-sweeper'),
            'manage_options',
            self::SLUG,
            [$this, 'render']
        );
    }

    public function render(): void
    {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Draft Sweeper', 'draft-sweeper'); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields('draft_sweeper');
                do_settings_sections(self::SLUG);
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    public function sanitize($input): array
    {
        $clean = [
            'enable_ai' => ! empty($input['enable_ai']),
            'scope' => ($input['scope'] ?? 'mine') === 'all' ? 'all' : 'mine',
            'completeness' => (float) ($input['completeness'] ?? 0.5),
            'recency' => (float) ($input['recency'] ?? 0.2),
            'relevance' => (float) ($input['relevance'] ?? 0.3),
        ];
        $sum = $clean['completeness'] + $clean['recency'] + $clean['relevance'];
        if ($sum > 0) {
            $clean['completeness'] /= $sum;
            $clean['recency'] /= $sum;
            $clean['relevance'] /= $sum;
        }
        return $clean;
    }

    public function fieldEnableAi(): void
    {
        $opt = get_option(self::OPTION, []);
        $checked = ! empty($opt['enable_ai']);
        echo '<label><input type="checkbox" name="' . esc_attr(self::OPTION) . '[enable_ai]" value="1" ' . checked($checked, true, false) . '> ';
        esc_html_e('Generate nudges using a configured AI Connector. Falls back to templates if none is configured.', 'draft-sweeper');
        echo '</label>';
    }

    public function fieldScope(): void
    {
        $opt = get_option(self::OPTION, []);
        $scope = $opt['scope'] ?? 'mine';
        echo '<select name="' . esc_attr(self::OPTION) . '[scope]">';
        printf('<option value="mine" %s>%s</option>', selected($scope, 'mine', false), esc_html__('My drafts only', 'draft-sweeper'));
        printf('<option value="all" %s>%s</option>', selected($scope, 'all', false), esc_html__('All site drafts', 'draft-sweeper'));
        echo '</select>';
    }

    public function fieldWeights(): void
    {
        $opt = get_option(self::OPTION, []);
        foreach (['completeness' => 0.5, 'recency' => 0.2, 'relevance' => 0.3] as $key => $default) {
            $value = (float) ($opt[$key] ?? $default);
            printf(
                '<p><label>%s <input type="number" step="0.05" min="0" max="1" name="%s[%s]" value="%s"></label></p>',
                esc_html(ucfirst($key)),
                esc_attr(self::OPTION),
                esc_attr($key),
                esc_attr((string) $value)
            );
        }
        echo '<p class="description">' . esc_html__('Values are normalized to sum to 1 on save.', 'draft-sweeper') . '</p>';
    }
}
