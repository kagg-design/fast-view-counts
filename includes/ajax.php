<?php

use KAGG\Fast_View_Counts\Main;

define( 'SHORTINIT', true );

// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash
$root = isset( $_SERVER['DOCUMENT_ROOT'] ) ? filter_var( $_SERVER['DOCUMENT_ROOT'], FILTER_SANITIZE_STRING ) : '';
require_once $root . 'wp-load.php';

//require_once $root . '/wp-includes/pluggable.php';
//require_once $root . '/wp-includes/user.php';

require_once '../classes/class-main.php';

if ( ! isset( $fast_view_counts ) ) {
	$fast_view_counts = new Main();
	$fast_view_counts->update_view_counts();
}


