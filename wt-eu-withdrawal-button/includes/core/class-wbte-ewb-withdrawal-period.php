<?php
/**
 * Withdrawal period start date helpers.
 *
 * Tracks when an order reaches configured statuses and resolves the
 * timestamp from which the withdrawal countdown begins.
 *
 * @package Wbte_Eu_Withdrawal_Button
 * @since   1.0.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Wbte_Ewb_Withdrawal_Period
 *
 * @since 1.0.4
 */
class Wbte_Ewb_Withdrawal_Period {

	/**
	 * Order meta prefix for first-seen status timestamps (UTC unix).
	 *
	 * @since 1.0.4
	 * @var string
	 */
	const STATUS_DATE_META_PREFIX = '_wbte_ewb_status_date_';

	/**
	 * Setting value for counting from order creation.
	 *
	 * @since 1.0.4
	 * @var string
	 */
	const START_ORDER_CREATED = 'order_created';

	/**
	 * Register hooks.
	 *
	 * @since 1.0.4
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'woocommerce_order_status_changed', array( __CLASS__, 'record_status_date' ), 10, 4 );
	}

	/**
	 * Return allowed values for withdrawal period start statuses.
	 *
	 * @since 1.0.4
	 *
	 * @return string[]
	 */
	public static function get_allowed_start_statuses() {
		$statuses = array( self::START_ORDER_CREATED );

		if ( function_exists( 'wc_get_order_statuses' ) ) {
			$statuses = array_merge( $statuses, array_keys( wc_get_order_statuses() ) );
		}

		return array_values( array_unique( $statuses ) );
	}

	/**
	 * Sanitize configured withdrawal period start statuses.
	 *
	 * @since 1.0.4
	 *
	 * @param mixed $value Raw input value.
	 * @return string[]
	 */
	public static function sanitize_start_statuses( $value ) {
		if ( ! is_array( $value ) ) {
			if ( is_string( $value ) && '' !== $value ) {
				$value = array( $value );
			} else {
				$value = array();
			}
		}

		$allowed = self::get_allowed_start_statuses();
		$clean   = array();

		foreach ( $value as $status ) {
			$status = sanitize_text_field( (string) $status );

			if ( in_array( $status, $allowed, true ) ) {
				$clean[] = $status;
			}
		}

		$clean = array_values( array_unique( $clean ) );

		if ( empty( $clean ) ) {
			$clean = array( self::START_ORDER_CREATED );
		}

		return $clean;
	}

	/**
	 * Return the configured withdrawal period start statuses.
	 *
	 * @since 1.0.4
	 *
	 * @return string[]
	 */
	public static function get_start_statuses() {
		$statuses = Wbte_Ewb_Settings::get( 'withdrawal_period_start_statuses', array() );

		if ( ! is_array( $statuses ) || empty( $statuses ) ) {
			$legacy = Wbte_Ewb_Settings::get( 'withdrawal_period_start_status', self::START_ORDER_CREATED );

			if ( is_array( $legacy ) ) {
				$statuses = $legacy;
			} elseif ( is_string( $legacy ) && '' !== $legacy ) {
				$statuses = array( $legacy );
			} else {
				$statuses = array( self::START_ORDER_CREATED );
			}
		}

		$statuses = self::sanitize_start_statuses( $statuses );

		/**
		 * Filters the order statuses used to start the withdrawal period countdown.
		 *
		 * @since 1.0.4
		 *
		 * @param string[] $statuses Configured start statuses.
		 */
		return (array) apply_filters( 'wbte_ewb_withdrawal_period_start_statuses', $statuses );
	}

	/**
	 * Store the first time an order entered a status.
	 *
	 * @since 1.0.4
	 *
	 * @param int      $order_id   Order ID.
	 * @param string   $old_status Previous status.
	 * @param string   $new_status New status.
	 * @param WC_Order $order      Order object.
	 * @return void
	 */
	public static function record_status_date( $order_id, $old_status, $new_status, $order ) {
		unset( $order_id, $old_status );

		if ( ! $order instanceof WC_Order ) {
			return;
		}

		$slug     = self::normalize_status_slug( $new_status );
		$meta_key = self::STATUS_DATE_META_PREFIX . $slug;

		if ( $order->get_meta( $meta_key, true ) ) {
			return;
		}

		$order->update_meta_data( $meta_key, time() );
		$order->save();
	}

	/**
	 * Resolve the UTC datetime when the withdrawal period starts for an order.
	 *
	 * Uses the earliest reached date among the configured start statuses.
	 *
	 * @since 1.0.4
	 *
	 * @param WC_Order $order WooCommerce order.
	 * @return WC_DateTime|null Null when none of the configured statuses has been reached.
	 */
	public static function get_period_start_date( $order ) {
		if ( ! $order instanceof WC_Order ) {
			return null;
		}

		$configured = self::get_start_statuses();
		$earliest   = null;

		foreach ( $configured as $status_key ) {
			$date = null;

			if ( self::START_ORDER_CREATED === $status_key ) {
				$date = $order->get_date_created();
			} else {
				$slug = self::normalize_status_slug( $status_key );

				if ( ! self::order_has_reached_status( $order, $slug ) ) {
					continue;
				}

				$date = self::get_status_start_date( $order, $slug );
			}

			if ( ! $date instanceof WC_DateTime ) {
				continue;
			}

			if ( null === $earliest || $date->getTimestamp() < $earliest->getTimestamp() ) {
				$earliest = $date;
			}
		}

		return $earliest;
	}

	/**
	 * Whether an order has reached a given status.
	 *
	 * @since 1.0.4
	 *
	 * @param WC_Order $order WooCommerce order.
	 * @param string   $slug  Status slug without wc- prefix.
	 * @return bool
	 */
	public static function order_has_reached_status( $order, $slug ) {
		if ( ! $order instanceof WC_Order ) {
			return false;
		}

		$slug = self::normalize_status_slug( $slug );

		if ( $order->get_status() === $slug ) {
			return true;
		}

		if ( absint( $order->get_meta( self::STATUS_DATE_META_PREFIX . $slug, true ) ) > 0 ) {
			return true;
		}

		$native = self::get_native_status_date( $order, $slug );

		if ( $native instanceof WC_DateTime ) {
			return true;
		}

		$downstream = array(
			'pending'    => array( 'pending', 'on-hold', 'processing', 'completed', 'withdrawn', 'pending-wdraw' ),
			'on-hold'    => array( 'on-hold', 'processing', 'completed', 'withdrawn', 'pending-wdraw' ),
			'processing' => array( 'processing', 'completed', 'withdrawn', 'pending-wdraw' ),
			'completed'  => array( 'completed', 'withdrawn' ),
		);

		if ( isset( $downstream[ $slug ] ) ) {
			return in_array( $order->get_status(), $downstream[ $slug ], true );
		}

		return false;
	}

	/**
	 * Resolve the start date for a single status slug on an order.
	 *
	 * @since 1.0.4
	 *
	 * @param WC_Order $order Order object.
	 * @param string   $slug  Status slug without wc- prefix.
	 * @return WC_DateTime|null
	 */
	private static function get_status_start_date( $order, $slug ) {
		$date = self::get_native_status_date( $order, $slug );

		if ( $date instanceof WC_DateTime ) {
			return $date;
		}

		$timestamp = absint( $order->get_meta( self::STATUS_DATE_META_PREFIX . $slug, true ) );

		if ( $timestamp > 0 ) {
			return new WC_DateTime( '@' . $timestamp, new DateTimeZone( 'UTC' ) );
		}

		$modified = $order->get_date_modified();

		return $modified instanceof WC_DateTime ? $modified : null;
	}

	/**
	 * Normalize a WooCommerce status slug.
	 *
	 * @since 1.0.4
	 *
	 * @param string $status Status slug, with or without wc- prefix.
	 * @return string
	 */
	private static function normalize_status_slug( $status ) {
		$status = sanitize_key( (string) $status );

		return str_replace( 'wc-', '', $status );
	}

	/**
	 * Return WooCommerce-native dates for well-known statuses.
	 *
	 * @since 1.0.4
	 *
	 * @param WC_Order $order Order object.
	 * @param string   $slug  Status slug without wc- prefix.
	 * @return WC_DateTime|null
	 */
	private static function get_native_status_date( $order, $slug ) {
		if ( 'completed' === $slug ) {
			$date = $order->get_date_completed();
			return $date instanceof WC_DateTime ? $date : null;
		}

		if ( 'processing' === $slug ) {
			$paid = $order->get_date_paid();
			return $paid instanceof WC_DateTime ? $paid : null;
		}

		return null;
	}
}
