<?php
/**
 * Review banner state and install / usage tracking.
 *
 * @package Wbte_Eu_Withdrawal_Button
 * @since   1.0.5
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Wbte_Ewb_Review_Banner
 *
 * @since 1.0.5
 */
class Wbte_Ewb_Review_Banner {

	/**
	 * Option key for the install timestamp.
	 *
	 * @since 1.0.5
	 * @var string
	 */
	const OPTION_INSTALL_TIME = 'wt_euwb_install_time';

	/**
	 * Option key for the withdrawal request counter.
	 *
	 * @since 1.0.5
	 * @var string
	 */
	const OPTION_WITHDRAWAL_COUNT = 'wt_euwb_withdrawal_count';

	/**
	 * Option key for the banner dismissal state.
	 *
	 * @since 1.0.5
	 * @var string
	 */
	const OPTION_STATUS = 'wt_euwb_review_banner_status';

	/**
	 * Option key for the remind-after timestamp when status is "later".
	 *
	 * @since 1.0.5
	 * @var string
	 */
	const OPTION_REMIND_AFTER = 'wt_euwb_review_banner_remind_after';

	/**
	 * Days after install before the time-based trigger applies.
	 *
	 * @since 1.0.5
	 * @var int
	 */
	const DAYS_THRESHOLD = 7;

	/**
	 * Withdrawal count required for the usage-based trigger.
	 *
	 * @since 1.0.5
	 * @var int
	 */
	const WITHDRAWALS_THRESHOLD = 10;

	/**
	 * Days to wait after "Remind me later" before showing again.
	 *
	 * @since 1.0.5
	 * @var int
	 */
	const LATER_DAYS = 15;

	/**
	 * Register hooks.
	 *
	 * @since 1.0.5
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'maybe_backfill_install_time' ) );
		add_action( 'admin_init', array( __CLASS__, 'maybe_backfill_withdrawal_count' ) );
	}

	/**
	 * Set install time on plugin activation when not already stored.
	 *
	 * @since 1.0.5
	 *
	 * @return void
	 */
	public static function set_install_time_on_activation() {
		if ( false === get_option( self::OPTION_INSTALL_TIME, false ) ) {
			update_option( self::OPTION_INSTALL_TIME, time(), false );
		}
	}

	/**
	 * Backfill install time for existing installs on first admin load.
	 *
	 * Never backdates — the clock starts from the update / first tracked moment.
	 *
	 * @since 1.0.5
	 *
	 * @return void
	 */
	public static function maybe_backfill_install_time() {
		if ( false !== get_option( self::OPTION_INSTALL_TIME, false ) ) {
			return;
		}

		update_option( self::OPTION_INSTALL_TIME, time(), false );
	}

	/**
	 * Backfill withdrawal count from the database on first admin load.
	 *
	 * @since 1.0.5
	 *
	 * @return void
	 */
	public static function maybe_backfill_withdrawal_count() {
		if ( false !== get_option( self::OPTION_WITHDRAWAL_COUNT, false ) ) {
			return;
		}

		$repository = function_exists( 'wbte_ewb' ) ? wbte_ewb()->get( 'repository' ) : null;
		$count      = 0;

		if ( $repository instanceof Wbte_Ewb_Request_Repository ) {
			$count = $repository->count_all();
		}

		update_option( self::OPTION_WITHDRAWAL_COUNT, absint( $count ), false );
	}

	/**
	 * Increment the withdrawal counter after a request is recorded.
	 *
	 * @since 1.0.5
	 *
	 * @return void
	 */
	public static function increment_withdrawal_count() {
		$count = (int) get_option( self::OPTION_WITHDRAWAL_COUNT, 0 );
		update_option( self::OPTION_WITHDRAWAL_COUNT, $count + 1, false );
	}

	/**
	 * WordPress.org review URL.
	 *
	 * @since 1.0.5
	 * @var string
	 */
	const REVIEW_URL = 'https://wordpress.org/support/plugin/wt-eu-withdrawal-button/reviews/#new-post';

	/**
	 * Whether the review prompt should render for the current user.
	 *
	 * @since 1.0.5
	 *
	 * @return bool
	 */
	public static function should_display() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return false;
		}

		$state = self::get_state();

		return ! empty( $state['show'] );
	}

	/**
	 * Get the localized milestone message.
	 *
	 * @since 1.0.5
	 *
	 * @param string $milestone Milestone slug (days|withdrawals).
	 * @return string
	 */
	public static function get_message( $milestone ) {
		if ( 'withdrawals' === $milestone ) {
			return __( 'Your store is making EU right-of-withdrawal easy for customers. Enjoying WebToffee EU Withdrawal Button? Leave a review!', 'wt-eu-withdrawal-button' );
		}

		return __( 'You\'ve been using WebToffee EU Withdrawal Button for a week. We\'d really appreciate if you could take a moment to leave us a review', 'wt-eu-withdrawal-button' );
	}

	/**
	 * Return banner state for the REST API.
	 *
	 * @since 1.0.5
	 *
	 * @return array<string, mixed>
	 */
	public static function get_state() {
		$milestone = self::get_trigger_milestone();
		$status    = self::get_status();

		return array(
			'show'      => self::should_show( $milestone, $status ),
			'milestone' => $milestone ? $milestone : '',
			'status'    => $status,
		);
	}

	/**
	 * Update banner state from a user action.
	 *
	 * @since 1.0.5
	 *
	 * @param string $action One of dismiss, review, or later.
	 * @return true|\WP_Error
	 */
	public static function handle_action( $action ) {
		switch ( $action ) {
			case 'dismiss':
			case 'review':
				update_option( self::OPTION_STATUS, $action, false );
				delete_option( self::OPTION_REMIND_AFTER );
				return true;

			case 'later':
				update_option( self::OPTION_STATUS, 'later', false );
				update_option( self::OPTION_REMIND_AFTER, time() + ( self::LATER_DAYS * DAY_IN_SECONDS ), false );
				return true;

			default:
				return new \WP_Error(
					'wbte_ewb_invalid_review_action',
					__( 'Invalid review banner action.', 'wt-eu-withdrawal-button' ),
					array( 'status' => 400 )
				);
		}
	}

	/**
	 * Get the stored banner status.
	 *
	 * @since 1.0.5
	 *
	 * @return string
	 */
	private static function get_status() {
		$status = get_option( self::OPTION_STATUS, 'pending' );

		if ( ! in_array( $status, array( 'pending', 'dismiss', 'dismissed', 'review', 'later' ), true ) ) {
			return 'pending';
		}

		return $status;
	}

	/**
	 * Determine whether a trigger milestone is currently met.
	 *
	 * @since 1.0.5
	 *
	 * @return string|null Milestone slug (days|withdrawals) or null when not eligible.
	 */
	private static function get_trigger_milestone() {
		$install_time = (int) get_option( self::OPTION_INSTALL_TIME, 0 );
		$count        = (int) get_option( self::OPTION_WITHDRAWAL_COUNT, 0 );

		$days_eligible        = $install_time > 0 && ( time() - $install_time ) >= ( self::DAYS_THRESHOLD * DAY_IN_SECONDS );
		$withdrawals_eligible = $count >= self::WITHDRAWALS_THRESHOLD;

		if ( ! $days_eligible && ! $withdrawals_eligible ) {
			return null;
		}

		if ( $withdrawals_eligible && ! $days_eligible ) {
			return 'withdrawals';
		}

		if ( $days_eligible && ! $withdrawals_eligible ) {
			return 'days';
		}

		// Both triggers are met — withdrawals wins when it fired within the first 7 days.
		if ( $install_time > 0 && ( time() - $install_time ) < ( self::DAYS_THRESHOLD * DAY_IN_SECONDS ) ) {
			return 'withdrawals';
		}

		return 'days';
	}

	/**
	 * Whether the banner should be shown for the current state.
	 *
	 * @since 1.0.5
	 *
	 * @param string|null $milestone Active milestone slug.
	 * @param string      $status    Stored banner status.
	 * @return bool
	 */
	private static function should_show( $milestone, $status ) {
		if ( ! $milestone ) {
			return false;
		}

		if ( in_array( $status, array( 'dismiss', 'dismissed', 'review' ), true ) ) {
			return false;
		}

		if ( 'later' === $status ) {
			$remind_after = (int) get_option( self::OPTION_REMIND_AFTER, 0 );

			if ( $remind_after > time() ) {
				return false;
			}

			update_option( self::OPTION_STATUS, 'pending', false );
			return true;
		}

		return 'pending' === $status;
	}
}
