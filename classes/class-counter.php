<?php
/**
 * Counter class file.
 *
 * @package KAGG\Fast_View_Counts
 */

namespace KAGG\Fast_View_Counts;

/**
 * Class Counter
 */
class Counter {

	/**
	 * Start of hour, unix timestamp.
	 *
	 * @var int
	 */
	public $hour;

	/**
	 * Hour counter.
	 *
	 * @var int
	 */
	public $hour_counter;

	/**
	 * Start of day, unix timestamp.
	 *
	 * @var int
	 */
	public $day;

	/**
	 * Day counter.
	 *
	 * @var int
	 */
	public $day_counter;

	/**
	 * Start of week, unix timestamp.
	 *
	 * @var int
	 */
	public $week;

	/**
	 * Week counter
	 *
	 * @var int
	 */
	public $week_counter;

	/**
	 * Start of month, unix timestamp.
	 *
	 * @var int
	 */
	public $month;

	/**
	 * Month counter.
	 *
	 * @var int
	 */
	public $month_counter;

	/**
	 * Start of total period, unix timestamp. Always 0.
	 *
	 * @var int
	 */
	public $total;

	/**
	 * Total counter.
	 *
	 * @var int
	 */
	public $total_counter;

	/**
	 * Date.
	 *
	 * @var array
	 */
	private $date;

	/**
	 * Counter constructor.
	 *
	 * @param string|null $string_data String data, maybe in json format.
	 */
	public function __construct( $string_data = null ) {
		$data = json_decode( $string_data );

		foreach ( get_object_vars( $this ) as $key => $value ) {
			$this->{$key} = isset( $data->{$key} ) ? $data->{$key} : 0;
		}

		// Compatibility with the old format with one number.
		$this->total_counter = is_int( $data ) ? $data : $this->total_counter;

		$this->date = getdate();
	}

	/**
	 * Increment all counters.
	 *
	 * @param int $value Value to increment by.
	 */
	public function increment( $value = 1 ) {
		foreach ( self::get_periods() as $period ) {
			$this->increment_counter( $period, $value );
		}
	}

	/**
	 * Get counting periods.
	 *
	 * @return array
	 */
	public static function get_periods() {
		return [ 'hour', 'day', 'week', 'month', 'total' ];
	}

	/**
	 * Get counter for period.
	 *
	 * @param string $period Period.
	 *
	 * @return int|false|string
	 */
	public function get( $period = '' ) {
		if ( in_array( $period, self::get_periods(), true ) ) {
			$period_counter = $period . '_counter';

			return $this->{$period_counter};
		}

		if ( '' === $period ) {
			// Only public properties appear in json string.
			return wp_json_encode( $this );
		}

		return 0;
	}

	/**
	 * Get total.
	 *
	 * @return int
	 */
	public function get_total() {
		return $this->total_counter;
	}

	/**
	 * Increment counter.
	 *
	 * @param string $period Period of the counter.
	 * @param int    $value  Value to increment by.
	 */
	private function increment_counter( $period, $value = 1 ) {
		$start_of_period = $this->get_start_of_period( $period );
		$period_counter  = $period . '_counter';
		if ( $this->{$period} === $start_of_period ) {
			$this->{$period_counter} += $value;
		} else {
			$this->{$period}         = $start_of_period;
			$this->{$period_counter} = 0;
		}
	}

	/**
	 * Get start of period.
	 *
	 * @param string $period Period of the counter.
	 *
	 * @return false|int
	 */
	public function get_start_of_period( $period ) {
		switch ( $period ) {
			case 'hour':
				return mktime( $this->date['hours'], 0, 0, $this->date['mon'], $this->date['mday'], $this->date['year'] );
			case 'day':
				return mktime( 0, 0, 0, $this->date['mon'], $this->date['mday'], $this->date['year'] );
			case 'week':
				$week_start = getdate( strtotime( '-' . ( $this->date['wday'] - 1 ) . ' days' ) );

				return mktime( 0, 0, 0, $week_start['mon'], $week_start['mday'], $week_start['year'] );
			case 'month':
				return mktime( 0, 0, 0, $this->date['mon'], 1, $this->date['year'] );
			default:
				return 0;
		}
	}
}
