<?php
/**
 * Abstract for various date-based Marketing notices, e.g. Black Friday sales or special coupon initiatives.
 *
 * @since TBD
 */

namespace Tribe\Admin\Notice;

use Tribe__Date_Utils as Dates;

abstract class Date_Based {
	/**
	 * The slug used to make filters specific to an individual notice.
	 *
	 * @since TBD
	 *
	 * @var string
	 */
	public $slug = '';

	/**
	 * Placeholder for start date string.
	 *
	 * @since TBD
	 *
	 * @var string
	 */
	public $start_date;

	/**
	 * Placeholder for start time int.
	 *
	 * @since TBD
	 *
	 * @var int
	 */
	public $start_time;

	/**
	 * Placeholder for end date string.
	 *
	 * @since TBD
	 *
	 * @var string
	 */
	public $end_date;

	/**
	 * Placeholder for end time int.
	 *
	 * @since TBD
	 *
	 * @var int
	 */
	public $end_time;

	/**
	 * Whether or not The Events Calendar is active.
	 *
	 * @since TBD
	 *
	 * @var boolean
	 */
	public $tec_is_active;

	/**
	 * Whether or not Event Tickets is active.
	 *
	 * @since TBD
	 *
	 * @var boolean
	 */
	public $et_is_active;

	public function __construct() {
		$tribe_dependency    = tribe( \Tribe__Dependency::class );
		$this->tec_is_active = $tribe_dependency->is_plugin_active( 'Tribe__Events__Main' );
		$this->et_is_active  = $tribe_dependency->is_plugin_active( 'Tribe__Tickets__Main' );

		$this->hook();
	}

	/**
	 * Register the various Marketing notices.
	 *
	 * @since TBD
	 */
	public function hook() {
		$this->hook_notice();
	}

	/**
	 * Register the notice.
	 *
	 * @since TBD
	 */
	public function hook_notice() {
		tribe_notice(
			$this->slug,
			[ $this, "display_notice" ],
			[
				'type'     => 'tribe-banner',
				'dismiss'  => 1,
				'priority' => -1,
				'wrap'     => false,
			],
			[ $this, "should_display" ]
		);
	}

	/**
	 * HTML for the notice.
	 *
	 * @since TBD
	 *
	 * @return string The HTML string to be displayed.
	 */
	abstract function display_notice();

	/**
	 * Whether the notice should display.
	 *
	 * @since TBD
	 *
	 * @return boolean $should_display Whether the notice should display or not.
	 */
	public function should_display() {
		// If upsells have been manually hidden, respect that.
		if ( defined( 'TRIBE_HIDE_UPSELL' ) && TRIBE_HIDE_UPSELL ) {
			return false;
		}

		$now           = Dates::build_date_object( 'now', 'UTC' )->format( 'U' );
		$notice_start = $this->get_start_time();
		$notice_end   = $this->get_end_time();

		$current_screen = get_current_screen();

		$screens = [
			'tribe_events_page_tribe-app-shop', // App shop.
			'events_page_tribe-app-shop', // App shop.
			'tribe_events_page_tribe-common', // Settings & Welcome.
			'events_page_tribe-common', // Settings & Welcome.
			'toplevel_page_tribe-common', // Settings & Welcome.
		];

		// If not a valid screen, don't display.
		if ( empty( $current_screen->id ) || ! in_array( $current_screen->id, $screens, true ) ) {
			return false;
		}

		$should_display = $notice_start <= $now && $now < $notice_end;

		/**
		 * Allow filtering of whether the notice should display.
		 *
		 * @since TBD
		 *
		 * @param boolean                          $should_display Whether the notice should display.
		 * @param Tribe__Admin__Notice__Date_Based $notice  The notice object.
		 */
		return apply_filters( "tribe_{$this->slug}_notice_should_display", $should_display, $this );
	}

	/**
	 * Unix time for notice start.
	 *
	 * @since TBD
	 *
	 * @return int $end_time The date & time the notice should start displaying, as a Unix timestamp.
	 */
	public function get_start_time() {
		$date = Dates::build_date_object( $this->start_date, 'UTC' );
		$date = $date->setTime( $this->start_time, 0 );

		/**
		* Allow filtering of the start date DateTime object,
		* to allow for things like "the day before" ( $date->modify( '-1 day' ) ) and such.
		*
		* @since TBD
		*
		* @param \DateTime $date Date object for the notice start.
		*/
		$date = apply_filters( "tribe_{$this->slug}_notice__start_date", $date );

		$start_time = $date->format( 'U' );

		/**
		 * Allow filtering of the "final" start date Unix timestamp, mainly for testing purposes.
		 *
		 * @since TBD
		 *
		 * @param int $start_time Unix timestamp for the when the notice starts.
		 */
		return apply_filters( "tribe_{$this->slug}_notice__start_time", $start_time );
	}

	/**
	 * Unix time for notice end.
	 *
	 * @since TBD
	 *
	 * @return int $end_time The date & time the notice should stop displaying, as a Unix timestamp.
	 */
	public function get_end_time() {
		$date = Dates::build_date_object( $this->end_date, 'UTC' );
		$date = $date->setTime( $this->end_time, 0 );

		/**
		* Allow filtering of the end date DateTime object,
		* to allow for things like "the day after" ( $date->modify( '+1 day' ) ) and such.
		*
		* @since TBD
		*
		* @param \DateTime $date Date object for the notice end.
		*/
		$date = apply_filters( "tribe_{$this->slug}_notice__end_date", $date );

		$end_time = $date->format( 'U' );

		/**
		 * Allow filtering of the final end date Unix timestamp, mainly for testing purposes.
		 *
		 * @since TBD
		 *
		 * @param int $end_time Unix timestamp for the when the notice ends.
		 */
		return apply_filters( "tribe_{$this->slug}_notice__end_time", $end_time );
	}
}
