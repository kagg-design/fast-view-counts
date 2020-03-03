<?php

use KAGG\Fast_View_Counts\Main;

define( 'SHORTINIT', true );

// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash
$root = isset( $_SERVER['DOCUMENT_ROOT'] ) ? filter_var( $_SERVER['DOCUMENT_ROOT'], FILTER_SANITIZE_STRING ) : '';

// Some servers return document root with trailing slash.
$root = rtrim( $root, '/\\' );
require $root . '/wp-load.php';

// Components needed for number_format_i18n() to work properly.
require $root . '/wp-includes/l10n.php';
require $root . '/wp-includes/class-wp-locale.php';

load_default_textdomain();
// phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited
$GLOBALS['locale']    = get_locale();
$GLOBALS['wp_locale'] = new WP_Locale();
// phpcs:enable WordPress.WP.GlobalVariablesOverride.Prohibited

// Components needed for check_ajax_referer() to work.
require $root . '/wp-includes/capabilities.php';
require $root . '/wp-includes/class-wp-roles.php';
require $root . '/wp-includes/class-wp-role.php';
require $root . '/wp-includes/class-wp-user.php';
require $root . '/wp-includes/user.php';
require $root . '/wp-includes/class-wp-session-tokens.php';
require $root . '/wp-includes/class-wp-user-meta-session-tokens.php';
require $root . '/wp-includes/kses.php';
require $root . '/wp-includes/rest-api.php';

wp_plugin_directory_constants();
wp_cookie_constants();

require $root . '/wp-includes/pluggable.php';

// Load main plugin class.
require_once '../classes/class-main.php';

if ( ! isset( $fast_view_counts ) ) {
	$fast_view_counts = new Main();
	$fast_view_counts->update_views();
}
