<?php
/**
 * Plugin Name: Fast View Counts
 * Description: Provides fast post/page view counts via ajax. Works well with full page caching.
 * Author: KAGG Design
 * Author URI: http://kagg.eu/en/
 * Version: 0.1
 * Plugin Slug: fast-view-counts
 * Requires at least: 5.3
 * Tested up to: 5.3
 * Requires PHP: 5.6
 *
 * Text Domain: fast-view-counts
 * Domain Path: /languages/
 *
 * @package fast-view-counts
 * @author  KAGG Design
 */

namespace KAGG\Fast_View_Counts;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! defined( 'FAST_VIEW_COUNTS_PATH' ) ) {
	/**
	 * Plugin path.
	 */
	define( 'FAST_VIEW_COUNTS_PATH', __DIR__ );
}

if ( ! defined( 'FAST_VIEW_COUNTS_URL' ) ) {
	/**
	 * Plugin url.
	 */
	define( 'FAST_VIEW_COUNTS_URL', untrailingslashit( plugin_dir_url( __FILE__ ) ) );
}

if ( ! defined( 'FAST_VIEW_COUNTS_FILE' ) ) {
	/**
	 * Plugin main file.
	 */
	define( 'FAST_VIEW_COUNTS_FILE', __FILE__ );
}

if ( ! defined( 'FAST_VIEW_COUNTS_VERSION' ) ) {
	/**
	 * Plugin version.
	 */
	define( 'FAST_VIEW_COUNTS_VERSION', '0.1' );
}

static $fast_view_counts;

require_once FAST_VIEW_COUNTS_PATH . '/classes/class-main.php';

if ( ! isset( $fast_view_counts ) ) {
	$fast_view_counts = new Main();
	$fast_view_counts->init();
}
