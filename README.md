# Draft Sweeper

A WordPress dashboard widget that resurfaces a single, curated abandoned draft each day. *"You started this 6 months ago and it's 80% done. Your readers would love it."*

Every day, Draft Sweeper picks **one** draft worth returning to. Drafts are scored on **completeness**, **recency**, and **topic relevance**; the highest-scoring non-dismissed draft wins. When an AI provider is configured via the WordPress 7.0 [Connectors API](https://make.wordpress.org/core/2026/03/18/introducing-the-connectors-api-in-wordpress-7-0/), the top three candidates plus your recent site topics are handed to the model so it can choose the most resonant one and write a richer nudge — token-budget conscious by design.

The pick is locked in for the day (rolling over at local midnight). "Save for later" gives you one re-pick; dismiss that too and the widget waits until tomorrow.

## Requirements

- WordPress 7.0+
- PHP 8.1+

## Install (development)

```bash
git clone https://github.com/annezazu/draft-sweeper.git
cd draft-sweeper
composer install --no-dev
```

Drop the directory into `wp-content/plugins/` and activate.

## Local testing with wp-now

```bash
cd /path/to/draft-sweeper
composer install
npx @wp-now/wp-now start --wp=7.0
```

`wp-now` mounts the current directory as a plugin (it has a plugin header) and boots an isolated WP 7.0 site. Then in another terminal:

```bash
# Seed sample drafts of varying age & length
npx @wp-now/wp-now run wp eval-file scripts/seed-drafts.php

# See the scored list from the CLI
npx @wp-now/wp-now run wp draft-sweeper top --limit=5
```

Open the admin dashboard — the **Draft Sweeper** widget should be at the top with the seeded drafts ranked.

To test the AI path, configure an Anthropic / OpenAI / Google key under **Settings → Connectors** (or set `ANTHROPIC_API_KEY` etc. in the env). Remove it to confirm the template fallback.

## WordPress Playground demo

A one-click demo runs entirely in the browser via [WordPress Playground](https://wordpress.github.io/wordpress-playground/):

```
https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/annezazu/draft-sweeper/main/playground/blueprint.json
```

(URL becomes valid after first public push.)

## How scoring works

`total = w_c · completeness + w_r · recency + w_t · relevance` (default weights 0.5 / 0.2 / 0.3, normalized).

| Component | Inputs | Notes |
| --- | --- | --- |
| Completeness | word count vs 800-word target, title, excerpt, featured image, categories + tags | 60% word-count, 10% each for the rest |
| Recency | days since last edit | Linear ramp 0→30 days, then **plateaus at 1.0 forever** — old drafts are not penalized |
| Relevance | cosine similarity of draft's term IDs vs aggregated terms of recently published posts (last 180 days) | No AI needed |

Settings → **Draft Sweeper** lets you tune weights, scope (mine vs all), and toggle AI nudges.

## Development

```bash
composer install
vendor/bin/phpunit          # unit tests covering scoring + summary fallback
```

The scoring engine lives in `src/Scoring/` and depends on nothing — pure PHP, fully unit-tested. WordPress integration is in `src/Drafts/`, `src/Dashboard/`, `src/Settings/`, `src/Cli/`, `src/Ai/`.

## License

GPL-2.0-or-later.
