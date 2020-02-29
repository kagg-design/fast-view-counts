<?php
/**
 * Fast_View_Counts main class file.
 *
 * @package kagg\fast-view-counts
 */

namespace KAGG\Fast_View_Counts;

use wpdb;

/**
 * Class Fast_View_Counts
 */
class Main {
	const COUNT_META_KEY = 'post_views_key_all';

	/**
	 * Counts read from database.
	 *
	 * @var array
	 */
	private $counts;

	/**
	 * Init class.
	 */
	public function init() {
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );

		add_action( 'wp_ajax_update_view_counts', [ $this, 'update_view_counts' ] );
		add_action( 'wp_ajax_nopriv_update_view_counts', [ $this, 'update_view_counts' ] );
	}

	/**
	 * Enqueue scripts.
	 */
	public function enqueue_scripts() {
		wp_enqueue_script(
			'update-view-counts',
			FAST_VIEW_COUNTS_URL . '/fast-view-counts.js',
			[ 'jquery' ],
			FAST_VIEW_COUNTS_VERSION,
			true
		);

		wp_localize_script(
			'update-view-counts',
			'update_view_counts',
			[
				'url'   => FAST_VIEW_COUNTS_URL . '/includes/ajax.php',
				'nonce' => wp_create_nonce( 'update_view_counts_nonce' ),
			]
		);
	}

	/**
	 * Get/update view counts.
	 */
	public function update_view_counts() {
		global $wpdb;

		check_ajax_referer( 'update_view_counts_nonce', 'nonce' );

		$views = filter_input( INPUT_POST, 'views', FILTER_VALIDATE_INT, FILTER_REQUIRE_ARRAY );
		$ids   = array_unique( array_column( $views, 'id' ) );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
		$prepare_in   = $this->prepare_in( $ids, '%d' );
		$this->counts = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE post_id IN (" .
				$prepare_in . ') AND meta_key = %s',
				self::COUNT_META_KEY
			)
		);

		foreach ( $views as $view ) {
			$count = $this->get_count( $view['id'] );
			if ( (bool) $view['update'] ) {
				$count ++;
				$wpdb->query(
					$wpdb->prepare(
						"UPDATE {$wpdb->postmeta} SET meta_value=%d WHERE post_id=%d AND meta_key=%s",
						$count,
						$view['id'],
						self::COUNT_META_KEY
					)
				);
			}
			$counts[] = $this->get_count_html( $count );
		}
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery

		$all_counts = [
			'counts' => $counts,
		];

		wp_send_json_success( $all_counts );
	}

	/**
	 * Get view count html.
	 *
	 * @param int $count Count.
	 *
	 * @return false|string
	 */
	private static function get_count_html( $count ) {
		ob_start();
		?>
		<span><?php echo esc_html( number_format_i18n( $count ) ); ?></span>
		<span class="view-svg-count"></span>
		<?php

		return ob_get_clean();
	}

	/**
	 * Show view count.
	 *
	 * @param int  $id     Post id.
	 * @param null $update Update or just show the count.
	 */
	public static function show_count( $id = 0, $update = null ) {
		$id     = $id ? $id : get_the_ID();
		$update = ( null !== $update ) ? $update : is_single();
		?>
		<div
				class="comments-view"
				data-view-count-id="<?php echo intval( $id ); ?>"
				data-view-count-update="<?php echo (int) $update; ?>"
		>
		</div>
		<?php
	}

	/**
	 * Changes array of items into string of items, separated by comma and sql-escaped
	 *
	 * @see https://coderwall.com/p/zepnaw
	 * @global wpdb       $wpdb
	 *
	 * @param mixed|array $items  item(s) to be joined into string.
	 * @param string      $format %s or %d.
	 *
	 * @return string Items separated by comma and sql-escaped
	 */
	private function prepare_in( $items, $format = '%s' ) {
		global $wpdb;

		$items    = (array) $items;
		$how_many = count( $items );
		if ( $how_many > 0 ) {
			$placeholders    = array_fill( 0, $how_many, $format );
			$prepared_format = implode( ',', $placeholders );
			// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
			$prepared_in = $wpdb->prepare( $prepared_format, $items );
			// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared
		} else {
			$prepared_in = '';
		}

		return $prepared_in;
	}

	/**
	 * Get count for post id.
	 *
	 * @param int $id Post id.
	 *
	 * @return int
	 */
	private function get_count( $id ) {
		foreach ( $this->counts as $count ) {
			if ( $id === (int) $count->post_id ) {
				return (int) $count->meta_value;
			}
		}

		return 0;
	}
}
