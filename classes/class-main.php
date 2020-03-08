<?php
/**
 * Fast_View_Counts main class file.
 *
 * @package KAGG\Fast_View_Counts
 */

namespace KAGG\Fast_View_Counts;

use wpdb;

/**
 * Class Main
 */
class Main {
	const COUNT_META_KEY = 'fast_view_count';

	const ICON                               = '
<svg width="16px" height="10px" viewBox="0 0 16 10" version="1.1" xmlns="http://www.w3.org/2000/svg"
     xmlns:xlink="http://www.w3.org/1999/xlink">
    <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
        <g transform="translate(-43.000000, -5.000000)" fill="#999999" fill-rule="nonzero">
            <path d="M51.0031165,15 C46.315933,15 43,11.1848635 43,10 C43,8.808933 46.2972341,5 51.0031165,5 C55.7401636,5 59,8.808933 59,10 C59,11.1848635 55.7463966,15 51.0031165,15 Z M51.0031165,13.2506203 C52.8169069,13.2506203 54.2754188,11.7679901 54.2754188,10 C54.2754188,8.18858561 52.8169069,6.75558313 51.0031165,6.75558313 C49.1768601,6.75558313 47.7183483,8.18238213 47.7245415,10 C47.7308142,11.7679901 49.1768601,13.2506203 51.0031165,13.2506203 Z M51.0031165,11.1848635 C50.3424231,11.1848635 49.8001558,10.6451613 49.8001558,10 C49.8001558,9.34863524 50.3424231,8.808933 51.0031165,8.808933 C51.6575769,8.808933 52.2060771,9.34863524 52.2060771,10 C52.2060771,10.6451613 51.6575769,11.1848635 51.0031165,11.1848635 Z"></path>
        </g>
    </g>
</svg>
	';
	const FAST_VIEW_COUNTS_META_KEY_CONSTANT = 'FAST_VIEW_COUNTS_META_KEY';

	/**
	 * Metas read from database.
	 *
	 * @var array
	 */
	private $metas;

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
		$prepared_ids       = $this->prepare_in( $ids, '%d' );
		$prepared_meta_keys = $this->prepare_in( $this->get_meta_keys(), '%s' );

		$this->metas = $wpdb->get_results(
			"SELECT post_id, meta_key, meta_value FROM {$wpdb->postmeta} " .
			'WHERE post_id IN (' . $prepared_ids . ') AND meta_key IN (' . $prepared_meta_keys . ')'
		);

		$counts = [];
		foreach ( $views as $view ) {
			$id      = $view['id'];
			$counter = $this->get_counter( $id );
			if ( (bool) $view['update'] ) {
				$counter = $this->maybe_fix_counter( $id, $counter );
				if ( 0 === $counter->get_total() ) {
					$counter->increment( wp_rand( 1, 4 ) );
					$this->add_metas( $id, $counter );
				} else {
					$counter->increment( wp_rand( 1, 4 ) );
					$this->update_metas( $id, $counter );
				}
			}
			$counts[] = $this->get_counter_inner_html( $counter->get_total() );
		}
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery

		return $counts;
	}

	/**
	 * Add metas.
	 *
	 * @param int     $id      Post id.
	 * @param Counter $counter Counter.
	 */
	private function add_metas( $id, $counter ) {
		global $wpdb;

		$periods = array_merge( [ '' ], Counter::get_periods() );

		foreach ( $periods as $period ) {
			$meta_key   = $this->get_meta_key( $period );
			$meta_value = $counter->get( $period );

			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
			// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared

			// Delete all keys for a case when several metas with the same key exist.
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->postmeta} WHERE post_id=%d AND meta_key=%s",
					$id,
					$meta_key
				)
			);
			$wpdb->query(
				$wpdb->prepare(
					"INSERT INTO {$wpdb->postmeta} (post_id, meta_key, meta_value) VALUES (%d, %s, %s)",
					$id,
					$meta_key,
					$meta_value
				)
			);
			// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery

		}

		wp_cache_delete( $id, 'post_meta' );
	}

	/**
	 * Update metas.
	 *
	 * @param int     $id      Post id.
	 * @param Counter $counter Counter.
	 */
	private function update_metas( $id, $counter ) {
		$periods = array_merge( [ '' ], Counter::get_periods() );

		foreach ( $periods as $period ) {
			update_post_meta( $id, $this->get_meta_key( $period ), $counter->get( $period ) );
		}
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
			$timestamp = $view['timestamp'];
			$dates[]   = $this->get_relative_date( $timestamp );
		}

		return $dates;
	}

	/**
	 * Get relative date.
	 *
	 * @param int $post_timestamp Post time stamp.
	 *
	 * @return false|int|string
	 */
	private function get_relative_date( $post_timestamp ) {
		$post_day      = wp_date( 'd', $post_timestamp );
		$post_hour_min = wp_date( 'H:i', $post_timestamp );

		$current_day = current_time( 'd', true );

		$utc = new \DateTimeZone( 'UTC' );

		$n_date = '';
		if ( wp_date( 'Y', $post_timestamp, $utc ) === gmdate( 'Y' ) ) {
			$time_diff = time() - $post_timestamp;
			if ( $time_diff < 3600 * 12 ) {
				$n_date = human_time_diff( $post_timestamp, time() ) . ' назад';
			} elseif ( ( $time_diff < 3600 * 24 ) && ( gmdate( 'd' ) === $post_day ) ) {
				$n_date = __( 'Сегодня', 'fast-view-counts' ) . ', ' . $post_hour_min;
			} elseif ( 1 === ( $current_day - $post_day ) ) {
				$n_date = __( 'Вчера', 'fast-view-counts' ) . ', ' . $post_hour_min;
			} elseif ( 2 === ( $current_day - $post_day ) ) {
				$n_date = __( 'Позавчера', 'fast-view-counts' ) . ', ' . $post_hour_min;
			}
			if ( '' === $n_date ) {
				$n_date = wp_date( 'j F', $post_timestamp );
			}
		}

		return ( '' !== $n_date ) ? $n_date : wp_date( 'd.m.Y', $post_timestamp );
	}

	/**
	 * Get view count html.
	 *
	 * @param int $count Count.
	 *
	 * @return false|string
	 */
	private static function get_counter_inner_html( $count ) {
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
	 * Get counter for post id.
	 *
	 * @param int $id Post id.
	 *
	 * @return Counter
	 */
	private function get_counter( $id ) {
		$counter = $this->get_counter_from_meta_key( $id, $this->get_meta_key() );
		if ( $counter ) {
			return $counter;
		}

		// Fallback to old format with numbers.
		$counter = new Counter();
		$periods = Counter::get_periods();
		foreach ( $periods as $period ) {
			$period_counter = $period . '_counter';

			$counter->{$period}         = $counter->get_start_of_period( $period );
			$counter->{$period_counter} = $this->get_count_from_meta_key( $id, $this->get_meta_key( $period ) );
		}

		// Fallback to old format with json in total.
		$total_counter_obj = json_decode( $counter->total_counter );
		if ( is_object( $total_counter_obj ) ) {
			$counter = new Counter( $counter->total_counter );

			$counter->total         = 0;
			$counter->total_counter = $total_counter_obj->total;
		}

		return $counter;
	}

	/**
	 * Maybe fix old counter.
	 *
	 * @param int     $id      Post id.
	 * @param Counter $counter Counter.
	 *
	 * @return Counter
	 */
	private function maybe_fix_counter( $id, $counter ) {
		if ( $counter->month_counter ) {
			return $counter;
		}

		// Fix counters for posts created in Mar, 2020.
		$sec_from_launch = strtotime( get_post( $id )->post_date_gmt ) - strtotime( '01-03-2020' );
		if ( $sec_from_launch > 0 ) {
			// Post was created after 01-Mar-2020.
			$sec_online = time() - strtotime( get_post( $id )->post_date_gmt );
			$rate       = $counter->total_counter / $sec_online; // Average views per second.

			$counter->hour_counter  = intval( min( $sec_online, 3600 ) * $rate * ( wp_rand( 75, 125 ) / 100 ) );
			$counter->day_counter   = intval( min( $sec_online, 3600 * 24 ) * $rate * ( wp_rand( 75, 125 ) / 100 ) );
			$counter->week_counter  = intval( min( $sec_online, 3600 * 24 * 7 ) * $rate * ( wp_rand( 75, 125 ) / 100 ) );
			$counter->month_counter = intval( min( $sec_online, 3600 * 24 * 30 ) * $rate * ( wp_rand( 75, 125 ) / 100 ) );

			$counter->hour_counter  = min( $counter->hour_counter, $counter->total_counter );
			$counter->day_counter   = max( min( $counter->day_counter, $counter->total_counter ), $counter->hour_counter );
			$counter->week_counter  = max( min( $counter->week_counter, $counter->total_counter ), $counter->day_counter );
			$counter->month_counter = max( min( $counter->month_counter, $counter->total_counter ), $counter->week_counter );
		}

		return $counter;
	}

	/**
	 * Get counter for post id from meta_key.
	 *
	 * @param int    $id       Post id.
	 * @param string $meta_key Meta key.
	 *
	 * @return Counter|null
	 */
	private function get_counter_from_meta_key( $id, $meta_key = '' ) {
		$count = $this->get_count_from_meta_key( $id, $meta_key );
		if ( null === $count ) {
			return null;
		}

		return new Counter( $count );
	}

	/**
	 * Get count for post id from meta_key.
	 *
	 * @param int    $id       Post id.
	 * @param string $meta_key Meta key.
	 *
	 * @return string|null
	 */
	private function get_count_from_meta_key( $id, $meta_key = '' ) {
		foreach ( $this->metas as $meta ) {
			if ( $id === (int) $meta->post_id && $meta_key === $meta->meta_key ) {
				return $meta->meta_value;
			}
		}

		return null;
	}

	/**
	 * Get meta key.
	 *
	 * @param string $period Period.
	 *
	 * @return string
	 */
	private function get_meta_key( $period = '' ) {
		$suffix    = $period ? '_' . $period : '';
		$suffix_up = strtoupper( $suffix );

		return (
		defined( self::FAST_VIEW_COUNTS_META_KEY_CONSTANT . $suffix_up ) ?
			(string) constant( self::FAST_VIEW_COUNTS_META_KEY_CONSTANT . $suffix_up ) :
			self::COUNT_META_KEY . $suffix
		);
	}

	/**
	 * Get all meta keys.
	 *
	 * @return array
	 */
	private function get_meta_keys() {
		$periods   = array_merge( [ '' ], Counter::get_periods() );
		$meta_keys = [];
		foreach ( $periods as $period ) {
			$meta_keys[] = $this->get_meta_key( $period );
		}

		return $meta_keys;
	}
}
