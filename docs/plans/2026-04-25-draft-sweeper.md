# Draft Sweeper Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** A WordPress plugin that adds a "Draft Sweeper" dashboard widget surfacing the most resurrectable abandoned drafts, scored by completeness + recency + topic relevance, with optional AI-generated nudges via the WP 7.0 Connectors API.

**Architecture:**
- Pure-PHP scoring engine (no WP dependencies → unit-testable with plain PHPUnit).
- WP integration layer: dashboard widget, settings page, AJAX endpoint, WP-CLI command.
- Connectors adapter: detects registered AI providers via `wp_get_connectors()`; calls them through `AiClient` when available; falls back to a deterministic templated nudge otherwise.

**Tech Stack:** PHP 8.1+, WordPress 7.0, WP AI Client, Composer + PHPUnit, wp-now (local), WordPress Playground (demo blueprint).

---

## Phase 0 — Scaffolding

### Task 0.1: Plugin skeleton + Git init
**Files:**
- Create: `draft-sweeper.php` (main plugin file with header)
- Create: `composer.json`
- Create: `.gitignore` (vendor/, .DS_Store, .wp-now, node_modules)
- Create: `README.md`
- Create: `LICENSE` (GPL-2.0-or-later)
- Create: `phpunit.xml.dist`

**Plugin header (draft-sweeper.php):**
```php
<?php
/**
 * Plugin Name: Draft Sweeper
 * Description: Resurfaces abandoned drafts intelligently in the dashboard.
 * Version: 0.1.0
 * Requires at least: 7.0
 * Requires PHP: 8.1
 * License: GPL-2.0-or-later
 */
defined( 'ABSPATH' ) || exit;
require_once __DIR__ . '/vendor/autoload.php';
\DraftSweeper\Plugin::boot( __FILE__ );
```

**composer.json:**
```json
{
  "name": "annemccarthy/draft-sweeper",
  "type": "wordpress-plugin",
  "require": { "php": ">=8.1" },
  "require-dev": { "phpunit/phpunit": "^10" },
  "autoload": { "psr-4": { "DraftSweeper\\": "src/" } },
  "autoload-dev": { "psr-4": { "DraftSweeper\\Tests\\": "tests/" } }
}
```

**Step:** `git init && git add . && git commit -m "chore: scaffold plugin"`

---

## Phase 1 — Scoring Engine (TDD, pure PHP)

### Task 1.1: `Score` value object
**Files:** Create `src/Scoring/Score.php`, `tests/Scoring/ScoreTest.php`

Pure data: `completeness`, `recency`, `relevance` (each 0..1), `total` (weighted sum). Frozen via readonly props.

### Task 1.2: `CompletenessScorer`
**Files:** Create `src/Scoring/CompletenessScorer.php`, `tests/Scoring/CompletenessScorerTest.php`

Inputs: `word_count`, `has_title`, `has_excerpt`, `has_featured_image`, `category_count`, `tag_count`, `target_word_count` (default 800).
Formula: `0.6 * min(word_count / target, 1.0) + 0.1*has_title + 0.1*has_excerpt + 0.1*has_featured_image + 0.1*min((cats+tags)/3, 1)`.

**Test cases:**
- 0-word empty draft → 0.0
- 800-word draft with title/excerpt/image/3 terms → 1.0
- 400-word title-only → ~0.4

### Task 1.3: `RecencyScorer`
**Files:** Create `src/Scoring/RecencyScorer.php`, `tests/Scoring/RecencyScorerTest.php`

Sweet spot: drafts modified 30–540 days ago score highest. Linear ramp up 0→30 days, plateau 30→540, linear decay 540→1095, 0 after 3 years. Inputs: `days_since_modified`.

**Test cases:** 0d→0, 90d→1.0, 540d→1.0, 1095d→0, 1500d→0.

### Task 1.4: `RelevanceScorer` (no AI)
**Files:** Create `src/Scoring/RelevanceScorer.php`, `tests/Scoring/RelevanceScorerTest.php`

Cosine-similarity of draft's term IDs (categories + tags) vs aggregated term IDs of recently published posts (last 6 months). Normalized 0..1.

**Test cases:** identical term sets → 1.0; disjoint → 0.0; 1-of-2 overlap → ~0.5.

### Task 1.5: `Weights` + `ScoreCalculator`
**Files:** Create `src/Scoring/Weights.php`, `src/Scoring/ScoreCalculator.php`, tests for both.

Default weights: completeness 0.5, recency 0.2, relevance 0.3. Settings page can override (Phase 5).

---

## Phase 2 — Draft Fetcher

### Task 2.1: `DraftRepository`
**Files:** Create `src/Drafts/DraftRepository.php`

Pulls drafts via `WP_Query`: `post_status=draft`, current user (filterable to all), limit 50 most-recently-modified. Returns lightweight DTOs (`DraftSnapshot`) with the fields the scoring engine needs — keeps WP coupling at the edge.

### Task 2.2: `RecentTopicsProvider`
**Files:** Create `src/Drafts/RecentTopicsProvider.php`

Returns aggregated category/tag IDs from `post_status=publish`, last 180 days. Cached via `get_transient` for 1 hour.

---

## Phase 3 — Connectors Adapter

### Task 3.1: `AiProviderResolver`
**Files:** Create `src/Ai/AiProviderResolver.php`

Calls `wp_get_connectors()`, filters `type === 'ai_provider'`, returns first connector with a configured API key (checks `setting_name` option AND `{ID}_API_KEY` env). Returns `null` if none — that's the no-AI path.

### Task 3.2: `NudgeGenerator` interface + two implementations
**Files:**
- `src/Ai/NudgeGenerator.php` (interface: `generate(DraftSnapshot, Score): string`)
- `src/Ai/AiNudgeGenerator.php` (uses `AiClient` with resolved provider)
- `src/Ai/TemplateNudgeGenerator.php` (deterministic, no-AI)

**Template fallback example:**
> "You started this **{age}** ago and it's **{completeness}%** done. Your readers who liked *{related_published_title}* would likely enjoy it."

`AiNudgeGenerator` prompt: short system message + draft excerpt + ask for one-sentence motivating nudge in user's voice. Wrapped in try/catch — falls back to template on any error.

**Tests:** Template generator is unit-tested with snapshots. AI generator gets a mock provider and asserts the prompt structure.

---

## Phase 4 — Dashboard Widget

### Task 4.1: `DashboardWidget`
**Files:** Create `src/Dashboard/DashboardWidget.php`, `assets/widget.css`, `assets/widget.js`

Hooks into `wp_dashboard_setup` → `wp_add_dashboard_widget('draft_sweeper', 'Draft Sweeper', ...)`. Renders top 5 drafts: title (link to edit), score bar, age, nudge, "Open" + "Dismiss" buttons.

### Task 4.2: AJAX dismiss + refresh
**Files:** Same widget class.

`wp_ajax_draft_sweeper_dismiss` stores dismissed IDs in user meta for 14 days. `wp_ajax_draft_sweeper_refresh` re-renders the list (returns HTML fragment). Nonces + capability checks (`edit_posts`).

---

## Phase 5 — Settings + WP-CLI

### Task 5.1: Settings page
**Files:** Create `src/Settings/SettingsPage.php`

Settings > Writing tab: enable/disable AI nudges, weight sliders (completeness/recency/relevance — must sum to 1, normalized on save), draft scope (mine vs all).

### Task 5.2: WP-CLI command
**Files:** Create `src/Cli/SweepCommand.php`

`wp draft-sweeper top [--user=<id>] [--limit=5]` — prints scored drafts as a table. Invaluable for testing without loading the dashboard.

---

## Phase 6 — Local Test Harness

### Task 6.1: wp-now smoke test
**Steps:**
```bash
npx @wp-now/wp-now start --wp=7.0
# wp-now mounts the current dir as a plugin since draft-sweeper.php has a plugin header
```

Manually:
1. Create 6 drafts of varying lengths/ages (use `wp post generate` via wp-now's CLI).
2. Open Dashboard → confirm widget appears with scored list.
3. Run `wp draft-sweeper top` → confirm CLI matches widget.
4. Configure an Anthropic API key under Settings > Connectors → confirm AI nudges replace template ones.
5. Remove the key → confirm graceful fallback.

### Task 6.2: Playground blueprint
**Files:** Create `playground/blueprint.json`

Declarative blueprint that boots WP 7.0, installs the plugin from a GitHub raw URL, and seeds 5 demo drafts via `setSiteOptions` + `runPHP`. Documented in README so anyone can click a Playground link to demo without installing anything.

---

## Phase 7 — Ship

### Task 7.1: README polish
Sections: what it does, screenshot/GIF, install (zip + composer), AI setup, Playground demo link, scoring formula, contributing.

### Task 7.2: Confirm before public push
Pause and confirm with user before `gh repo create annemccarthy/draft-sweeper --public --source=. --push`. Until then stays a local repo only.

---

## Out of scope (YAGNI)
- Multisite support
- i18n beyond `__()` wrappers (English only for v0.1)
- Block editor sidebar plugin (dashboard widget is enough for v1)
- Vector-embedding-based relevance (term overlap is good enough; can add later)
- Bulk actions / scheduling re-publication
