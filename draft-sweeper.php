<?php
/**
 * Plugin Name:       Draft Sweeper
 * Plugin URI:        https://github.com/annezazu/draft-sweeper
 * Description:       Resurfaces abandoned drafts intelligently in the dashboard, with optional AI-generated nudges via the WordPress 7.0 Connectors API.
 * Version:           0.5.0
 * Requires at least: 7.0
 * Requires PHP:      8.1
 * Author:            annezazu
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       draft-sweeper
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/vendor/autoload.php';

\DraftSweeper\Plugin::boot( __FILE__ );
