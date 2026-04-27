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

spl_autoload_register( static function ( $class ) {
	$prefix = 'DraftSweeper\\';
	if ( strncmp( $class, $prefix, strlen( $prefix ) ) !== 0 ) {
		return;
	}
	$file = __DIR__ . '/src/' . str_replace( '\\', '/', substr( $class, strlen( $prefix ) ) ) . '.php';
	if ( is_file( $file ) ) {
		require $file;
	}
} );

\DraftSweeper\Plugin::boot( __FILE__ );
