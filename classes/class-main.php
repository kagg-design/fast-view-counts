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

	/**
	 * Meta key.
	 */
	const COUNT_META_KEY = 'fast_view_count';

	/**
	 * Views icon.
	 */
	const ICON = '
<svg width="16px" height="10px" viewBox="0 0 16 10" version="1.1" xmlns="http://www.w3.org/2000/svg"
     xmlns:xlink="http://www.w3.org/1999/xlink">
    <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
        <g transform="translate(-43.000000, -5.000000)" fill="#999999" fill-rule="nonzero">
            <path d="M51.0031165,15 C46.315933,15 43,11.1848635 43,10 C43,8.808933 46.2972341,5 51.0031165,5 C55.7401636,5 59,8.808933 59,10 C59,11.1848635 55.7463966,15 51.0031165,15 Z M51.0031165,13.2506203 C52.8169069,13.2506203 54.2754188,11.7679901 54.2754188,10 C54.2754188,8.18858561 52.8169069,6.75558313 51.0031165,6.75558313 C49.1768601,6.75558313 47.7183483,8.18238213 47.7245415,10 C47.7308142,11.7679901 49.1768601,13.2506203 51.0031165,13.2506203 Z M51.0031165,11.1848635 C50.3424231,11.1848635 49.8001558,10.6451613 49.8001558,10 C49.8001558,9.34863524 50.3424231,8.808933 51.0031165,8.808933 C51.6575769,8.808933 52.2060771,9.34863524 52.2060771,10 C52.2060771,10.6451613 51.6575769,11.1848635 51.0031165,11.1848635 Z"></path>
        </g>
    </g>
</svg>
	';

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

		add_action( 'wp_ajax_update_view_counts', [ $this, 'update_views' ] );
		add_action( 'wp_ajax_nopriv_update_view_counts', [ $this, 'update_views' ] );
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
	 * Get/update views.
	 */
	public function update_views() {
		check_ajax_referer( 'update_view_counts_nonce', 'nonce' );

		wp_send_json_success(
			[
				'counts' => $this->get_view_counts(),
				'dates'  => $this->get_view_dates(),
			]
		);
	}

	/**
	 * Get/update view counts.
	 *
	 * @return array
	 */
	private function get_view_counts() {
		global $wpdb;

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
				$this->get_meta_key()
			)
		);

		foreach ( $views as $view ) {
			$id    = $view['id'];
			$count = $this->get_count( $id );
			if ( (bool) $view['update'] ) {
				$count ++;
				if ( 1 === $count ) {
					$wpdb->query(
						$wpdb->prepare(
							"INSERT INTO {$wpdb->postmeta} (post_id, meta_key, meta_value) VALUES (%d, %s, %d)",
							$id,
							$this->get_meta_key(),
							$count
						)
					);
				} else {
					$wpdb->query(
						$wpdb->prepare(
							"UPDATE {$wpdb->postmeta} SET meta_value=meta_value+1 WHERE post_id=%d AND meta_key=%s",
							$id,
							$this->get_meta_key()
						)
					);
				}
			}
			$counts[] = $this->get_count_inner_html( $count );
		}
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery

		return $counts;
	}

	/**
	 * Get view dates.
	 *
	 * @return array
	 */
	private function get_view_dates() {
		$dates = [];

		$views = filter_input( INPUT_POST, 'dates', FILTER_VALIDATE_INT, FILTER_REQUIRE_ARRAY );

		foreach ( $views as $view ) {
			$id      = $view['id'];
			$dates[] = $this->get_relative_date( $id );
		}

		return $dates;
	}

	/**
	 * Get relative date.
	 *
	 * @param int $post_id Post id.
	 *
	 * @return false|int|string
	 */
	private function get_relative_date( $post_id ) {
		$n_date = '';
		if ( get_the_date( 'Y', $post_id ) === date( 'Y' ) ) {
			$unix_time = current_time( 'timestamp' ) - get_post_time( 'U', false, $post_id );
			if ( $unix_time < 3600 * 12 ) {
				$n_date = human_time_diff( strtotime( get_the_date( 'd.m.Y, H:i', $post_id ) ), current_time( 'timestamp' ) ) . ' назад';
			} elseif ( ( $unix_time < 3600 * 24 ) && ( get_the_date( 'd', $post_id ) === date( 'd' ) ) ) {
				$n_date = __( 'Сегодня', 'shesht' ) . ', ' . get_post_time( 'H:i', false, $post_id, true );
			} elseif ( 1 === ( current_time( 'd' ) - get_the_date( 'd', $post_id ) ) ) {
				$n_date = __( 'Вчера', 'shesht' ) . ', ' . get_post_time( 'H:i', false, $post_id, true );
			} elseif ( 2 === ( current_time( 'd' ) - get_the_date( 'd', $post_id ) ) ) {
				$n_date = __( 'Позавчера', 'shesht' ) . ', ' . get_post_time( 'H:i', false, $post_id, true );
			}
			if ( '' === $n_date ) {
				$n_date = get_post_time( 'j F', false, $post_id, true );
			}
		}

		return ( '' !== $n_date ) ? $n_date : get_post_time( 'd.m.Y', false, $post_id, true );
	}

	/**
	 * Get view count html.
	 *
	 * @param int $count Count.
	 *
	 * @return false|string
	 */
	private static function get_count_inner_html( $count ) {
		$html = '<span class="fvc-count">' . number_format_i18n( $count ) . '</span>';

		$html .= '<span class="fvc-icon">' . self::ICON . '</span>';

		return $html;
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
				class="fvc-view"
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

	/**
	 * Get meta key.
	 *
	 * @return string
	 */
	private function get_meta_key() {
		return (
		constant( 'FAST_VIEW_COUNTS_META_KEY' ) ?
			(string) constant( 'FAST_VIEW_COUNTS_META_KEY' ) :
			self::COUNT_META_KEY
		);
	}
}
