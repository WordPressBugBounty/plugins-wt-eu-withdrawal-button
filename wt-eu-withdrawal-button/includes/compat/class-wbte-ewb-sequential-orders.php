<?php
/**
 * Sequential order number compatibility.
 *
 * Provides helpers that work correctly with WooCommerce sequential
 * order number plugins (e.g., WooCommerce Sequential Order Numbers).
 *
 * @package Wbte_Eu_Withdrawal_Button
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Wbte_Ewb_Sequential_Orders
 *
 * @since 1.0.0
 */
class Wbte_Ewb_Sequential_Orders {

	/**
	 * Retrieve the display order number for an order.
	 *
	 * This respects sequential order number plugins by using
	 * the WooCommerce `get_order_number()` method rather than
	 * the raw post/order ID.
	 *
	 * @since 1.0.0
	 *
	 * @param \WC_Order $order The WooCommerce order.
	 * @return string The display order number.
	 */
	public function get_order_number( $order ) {
		if ( ! $order instanceof \WC_Order ) {
			return '';
		}

		return $order->get_order_number();
	}

	/**
	 * Find a WooCommerce order by its display order number.
	 *
	 * Tries a direct lookup first (order number === order ID).
	 * If that does not match, falls back to searching by the
	 * `_order_number` meta key used by sequential number plugins.
	 *
	 * @since 1.0.0
	 *
	 * @param string $order_number The display order number.
	 * @return \WC_Order|null The order or null if not found.
	 */
	public function find_order_by_number( $order_number ) {
		if ( empty( $order_number ) ) {
			return null;
		}

		// Attempt direct lookup (works when no sequential plugin is active).
		$order = wc_get_order( $order_number );

		if ( $order instanceof \WC_Order ) {
			// Verify the display number matches to avoid false positives.
			if ( (string) $order->get_order_number() === (string) $order_number ) {
				return $order;
			}
		}

		// Fallback: search by _order_number meta (sequential number plugins).
		$orders = wc_get_orders(
			array(
				'limit'      => 1,
				'meta_key'   => '_order_number', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value' => sanitize_text_field( $order_number ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'return'     => 'objects',
			)
		);

		if ( ! empty( $orders ) && $orders[0] instanceof \WC_Order ) {
			return $orders[0];
		}

		return null;
	}
}
