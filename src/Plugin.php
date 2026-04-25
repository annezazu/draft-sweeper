<?php
declare(strict_types=1);

namespace DraftSweeper;

use DraftSweeper\Ai\AiNudgeGenerator;
use DraftSweeper\Ai\AiProviderResolver;
use DraftSweeper\Ai\NudgeGenerator;
use DraftSweeper\Ai\TemplateNudgeGenerator;
use DraftSweeper\Cli\SweepCommand;
use DraftSweeper\Dashboard\DashboardWidget;
use DraftSweeper\Drafts\DraftRepository;
use DraftSweeper\Drafts\RecentTopicsProvider;
use DraftSweeper\Scoring\ScoreCalculator;
use DraftSweeper\Scoring\Weights;
use DraftSweeper\Settings\SettingsPage;

final class Plugin
{
    private static ?self $instance = null;

    public static function boot(string $pluginFile): void
    {
        if (self::$instance !== null) {
            return;
        }
        self::$instance = new self($pluginFile);
        self::$instance->registerHooks();
    }

    public static function instance(): self
    {
        if (self::$instance === null) {
            throw new \RuntimeException('Plugin not booted.');
        }
        return self::$instance;
    }

    private function __construct(private readonly string $pluginFile)
    {
    }

    public function pluginFile(): string
    {
        return $this->pluginFile;
    }

    public function pluginUrl(string $relative = ''): string
    {
        return plugins_url($relative, $this->pluginFile);
    }

    public function settings(): array
    {
        $defaults = [
            'enable_ai'    => true,
            'scope'        => 'mine',
            'completeness' => 0.5,
            'recency'      => 0.2,
            'relevance'    => 0.3,
        ];
        $stored = get_option('draft_sweeper_settings', []);
        return array_merge($defaults, is_array($stored) ? $stored : []);
    }

    public function weights(): Weights
    {
        $s = $this->settings();
        return (new Weights(
            (float) $s['completeness'],
            (float) $s['recency'],
            (float) $s['relevance'],
        ))->normalized();
    }

    public function calculator(): ScoreCalculator
    {
        return new ScoreCalculator($this->weights());
    }

    public function repository(): DraftRepository
    {
        return new DraftRepository();
    }

    public function topicsProvider(): RecentTopicsProvider
    {
        return new RecentTopicsProvider();
    }

    public function nudgeGenerator(): NudgeGenerator
    {
        $template = new TemplateNudgeGenerator();
        if (! $this->settings()['enable_ai']) {
            return $template;
        }
        return new AiNudgeGenerator(new AiProviderResolver(), $template);
    }

    private function registerHooks(): void
    {
        $widget = new DashboardWidget($this);
        add_action('wp_dashboard_setup', [$widget, 'register']);
        add_action('admin_enqueue_scripts', [$widget, 'enqueueAssets']);
        add_action('wp_ajax_draft_sweeper_dismiss', [$widget, 'ajaxDismiss']);
        add_action('wp_ajax_draft_sweeper_refresh', [$widget, 'ajaxRefresh']);

        $settings = new SettingsPage();
        add_action('admin_init', [$settings, 'register']);
        add_action('admin_menu', [$settings, 'addMenu']);

        if (defined('WP_CLI') && \WP_CLI) {
            \WP_CLI::add_command('draft-sweeper', new SweepCommand($this));
        }
    }
}
