<?php
/**
 * Withdrawal eligibility checker.
 *
 * Determines whether orders and individual line items are
 * eligible for withdrawal based on plugin settings.
 *
 * @package Wbte_Eu_Withdrawal_Button
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Wbte_Ewb_Eligibility
 *
 * @since 1.0.0
 */
class Wbte_Ewb_Eligibility {

	/**
	 * Check whether an order is eligible for withdrawal.
	 *
	 * Conditions:
	 * - Order must exist.
	 * - Order must be within the configured withdrawal period from the selected start status.
	 * - Order must not already be in withdrawn status unless partial withdrawals
	 *   are enabled and withdrawable quantity remains.
	 * - When partial withdrawals are enabled, the order must still have remaining
	 *   withdrawable quantity on at least one eligible line item.
	 * - When partial withdrawals are disabled, the order must not already be in
	 *   pending-wdraw status or have a pending/approved withdrawal request.
	 *
	 * @since 1.0.0
	 *
	 * @param \WC_Order $order The WooCommerce order.
	 * @return bool
	 */
	public function is_order_eligible( $order ) {
		if ( ! $order instanceof \WC_Order ) {
			return false;
		}

		$status = $order->get_status();

		if ( 'trash' === $status ) {
			/** This filter is documented below. */
			return apply_filters( 'wbte_ewb_order_eligible', false, $order );
		}

		$allow_partial = 'yes' === Wbte_Ewb_Settings::get( 'allow_partial_withdrawals', 'yes' );

		if ( 'withdrawn' === $status ) {
			if ( ! $allow_partial || ! $this->has_remaining_withdrawable_quantity( $order ) ) {
				$eligible = false;

				/** This filter is documented below. */
				return apply_filters( 'wbte_ewb_order_eligible', $eligible, $order );
			}
		}

		if ( ! $allow_partial && 'pending-wdraw' === $status ) {
			$eligible = false;

			/** This filter is documented below. */
			return apply_filters( 'wbte_ewb_order_eligible', $eligible, $order );
		}

		// Check withdrawal period.
		$withdrawal_period = (int) Wbte_Ewb_Settings::get( 'withdrawal_period', 14 );
		$period_start      = Wbte_Ewb_Withdrawal_Period::get_period_start_date( $order );

		if ( ! $period_start instanceof \WC_DateTime ) {
			$eligible = false;

			/** This filter is documented below. */
			return apply_filters( 'wbte_ewb_order_eligible', $eligible, $order );
		}

		$order_timestamp = $period_start->getTimestamp();
		$deadline        = $order_timestamp + ( $withdrawal_period * DAY_IN_SECONDS );
		$now             = current_time( 'timestamp', true );

		if ( $now > $deadline ) {
			$eligible = false;

			/** This filter is documented below. */
			return apply_filters( 'wbte_ewb_order_eligible', $eligible, $order );
		}

		if ( $allow_partial ) {
			$eligible = $this->has_remaining_withdrawable_quantity( $order );
		} else {
			$eligible = ! $this->has_active_request( $order->get_id() );
		}

		/**
		 * Filters whether an order is eligible for withdrawal.
		 *
		 * @since 1.0.0
		 *
		 * @param bool      $eligible Whether the order is eligible.
		 * @param \WC_Order $order    The WooCommerce order.
		 */
		return apply_filters( 'wbte_ewb_order_eligible', $eligible, $order );
	}

	/**
	 * Get eligible line items for an order.
	 *
	 * Excludes items matching excluded product types, categories, and products.
	 *
	 * @since 1.0.0
	 *
	 * @param \WC_Order $order The WooCommerce order.
	 * @return array Eligible line items.
	 */
	public function get_eligible_items( $order ) {
		if ( ! $order instanceof \WC_Order ) {
			return array();
		}

		$eligible_items = array();

		foreach ( $order->get_items() as $item_id => $item ) {
			// Skip bundled child items (WooCommerce Product Bundles compatibility).
			if ( $item->get_meta( '_bundled_by', true ) ) {
				continue;
			}

			$product = $item->get_product();

			if ( ! $product || '' !== $this->get_product_exclusion_reason( $product ) ) {
				continue;
			}

			$eligible_items[ $item_id ] = $item;
		}

		/**
		 * Filters the eligible items for a withdrawal request.
		 *
		 * @since 1.0.0
		 *
		 * @param array     $eligible_items Eligible line items.
		 * @param \WC_Order $order          The WooCommerce order.
		 */
		return apply_filters( 'wbte_ewb_request_eligible_items', $eligible_items, $order );
	}

	/**
	 * Get pending and approved withdrawal requests for an order.
	 *
	 * @since 1.0.4
	 *
	 * @param int $order_id WooCommerce order ID.
	 * @return Wbte_Ewb_Request[]
	 */
	public function get_active_requests( $order_id ) {
		$repository = function_exists( 'wbte_ewb' ) ? wbte_ewb()->get( 'repository' ) : null;

		if ( ! $repository instanceof Wbte_Ewb_Request_Repository ) {
			return array();
		}

		return $repository->find_active_by_order( absint( $order_id ) );
	}

	/**
	 * Sum quantities already committed on pending/approved requests, keyed by line item ID.
	 *
	 * @since 1.0.4
	 *
	 * @param int $order_id WooCommerce order ID.
	 * @return array<int, int>
	 */
	public function get_committed_quantities( $order_id ) {
		$committed = array();
		$order_id  = absint( $order_id );

		foreach ( $this->get_active_requests( $order_id ) as $request ) {
			$items = $request->get_items();

			if ( empty( $items ) && 'full' === $request->request_type ) {
				$order = wc_get_order( $order_id );

				if ( $order instanceof \WC_Order ) {
					foreach ( $this->get_eligible_items( $order ) as $line_item_id => $line_item ) {
						if ( ! $line_item instanceof \WC_Order_Item_Product ) {
							continue;
						}

						$qty = absint( $line_item->get_quantity() );

						if ( $qty < 1 ) {
							continue;
						}

						if ( ! isset( $committed[ $line_item_id ] ) ) {
							$committed[ $line_item_id ] = 0;
						}

						$committed[ $line_item_id ] += $qty;
					}
				}

				continue;
			}

			foreach ( $items as $item ) {
				$line_item_id = isset( $item['line_item_id'] ) ? absint( $item['line_item_id'] ) : 0;

				if ( ! $line_item_id ) {
					continue;
				}

				if ( isset( $item['qty'] ) ) {
					$qty = absint( $item['qty'] );
				} elseif ( isset( $item['quantity'] ) ) {
					$qty = absint( $item['quantity'] );
				} else {
					$qty = 0;
				}

				if ( $qty < 1 ) {
					continue;
				}

				if ( ! isset( $committed[ $line_item_id ] ) ) {
					$committed[ $line_item_id ] = 0;
				}

				$committed[ $line_item_id ] += $qty;
			}
		}

		/**
		 * Filters committed withdrawal quantities for an order.
		 *
		 * @since 1.0.4
		 *
		 * @param array<int, int> $committed Quantities keyed by line item ID.
		 * @param int             $order_id  WooCommerce order ID.
		 */
		return apply_filters( 'wbte_ewb_committed_quantities', $committed, $order_id );
	}

	/**
	 * Get remaining withdrawable quantity per eligible line item.
	 *
	 * @since 1.0.4
	 *
	 * @param \WC_Order $order WooCommerce order.
	 * @return array<int, int> Remaining quantity keyed by line item ID.
	 */
	public function get_remaining_quantities( $order ) {
		if ( ! $order instanceof \WC_Order ) {
			return array();
		}

		$committed = $this->get_committed_quantities( $order->get_id() );
		$remaining = array();

		foreach ( $order->get_items() as $item_id => $item ) {
			if ( ! $item instanceof \WC_Order_Item_Product ) {
				continue;
			}

			// Skip bundled child items (WooCommerce Product Bundles compatibility).
			if ( $item->get_meta( '_bundled_by', true ) ) {
				continue;
			}

			$product = $item->get_product();

			if ( ! $product || '' !== $this->get_product_exclusion_reason( $product ) ) {
				continue;
			}

			$ordered_qty = absint( $item->get_quantity() );
			$used_qty    = isset( $committed[ $item_id ] ) ? absint( $committed[ $item_id ] ) : 0;

			$remaining[ $item_id ] = max( 0, $ordered_qty - $used_qty );
		}

		/**
		 * Filters remaining withdrawable quantities for an order.
		 *
		 * @since 1.0.4
		 *
		 * @param array<int, int> $remaining Remaining quantity keyed by line item ID.
		 * @param \WC_Order       $order     WooCommerce order.
		 * @param array<int, int> $committed Committed quantity keyed by line item ID.
		 */
		return apply_filters( 'wbte_ewb_remaining_quantities', $remaining, $order, $committed );
	}

	/**
	 * Check whether an order still has withdrawable quantity available.
	 *
	 * @since 1.0.4
	 *
	 * @param \WC_Order $order WooCommerce order.
	 * @return bool
	 */
	public function has_remaining_withdrawable_quantity( $order ) {
		foreach ( $this->get_remaining_quantities( $order ) as $remaining_qty ) {
			if ( $remaining_qty > 0 ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get the remaining withdrawable quantity for a single line item.
	 *
	 * @since 1.0.4
	 *
	 * @param \WC_Order $order        WooCommerce order.
	 * @param int       $line_item_id Order line item ID.
	 * @return int
	 */
	public function get_remaining_quantity_for_line_item( $order, $line_item_id ) {
		$remaining = $this->get_remaining_quantities( $order );

		return isset( $remaining[ $line_item_id ] ) ? absint( $remaining[ $line_item_id ] ) : 0;
	}

	/**
	 * Return the exclusion reason for a product, or an empty string when eligible.
	 *
	 * @since 1.0.1
	 *
	 * @param \WC_Product $product Product object.
	 * @return string
	 */
	public function get_product_exclusion_reason( $product ) {
		if ( ! $product instanceof \WC_Product ) {
			return '';
		}

		$excluded_types      = (array) Wbte_Ewb_Settings::get( 'excluded_product_types', array() );
		$excluded_categories = array_map( 'absint', (array) Wbte_Ewb_Settings::get( 'excluded_categories', array() ) );
		$excluded_products   = array_map( 'absint', (array) Wbte_Ewb_Settings::get( 'excluded_products', array() ) );

		if ( ! empty( $excluded_types ) ) {
			$product_type = $product->get_type();

			if ( in_array( $product_type, $excluded_types, true ) ) {
				return __( 'This product type cannot be withdrawn.', 'wt-eu-withdrawal-button' );
			}

			if ( in_array( 'virtual', $excluded_types, true ) && $product->is_virtual() ) {
				return __( 'Virtual products cannot be withdrawn.', 'wt-eu-withdrawal-button' );
			}

			if ( in_array( 'downloadable', $excluded_types, true ) && $product->is_downloadable() ) {
				return __( 'Downloadable products cannot be withdrawn.', 'wt-eu-withdrawal-button' );
			}
		}

		if ( ! empty( $excluded_products ) ) {
			$product_id = absint( $product->get_id() );
			$parent_id  = absint( $product->get_parent_id() );

			if ( in_array( $product_id, $excluded_products, true ) || ( $parent_id && in_array( $parent_id, $excluded_products, true ) ) ) {
				return __( 'This product cannot be withdrawn.', 'wt-eu-withdrawal-button' );
			}
		}

		if ( ! empty( $excluded_categories ) ) {
			$product_id   = $product->is_type( 'variation' ) ? $product->get_parent_id() : $product->get_id();
			$category_ids = wp_get_post_terms( $product_id, 'product_cat', array( 'fields' => 'ids' ) );

			if ( ! is_wp_error( $category_ids ) && array_intersect( array_map( 'absint', $category_ids ), $excluded_categories ) ) {
				return __( 'Products in this category cannot be withdrawn.', 'wt-eu-withdrawal-button' );
			}
		}

		return '';
	}

	/**
	 * Get all eligible orders for a given customer.
	 *
	 * Supports both logged-in users (by ID) and guest customers (by email).
	 *
	 * @since 1.0.0
	 *
	 * @param int|string $customer_id_or_email Customer user ID (int) or email (string).
	 * @return \WC_Order[] Array of eligible WooCommerce orders.
	 */
	public function get_eligible_orders_for_customer( $customer_id_or_email ) {
		$args = array(
			'limit'   => -1,
			'return'  => 'objects',
			'orderby' => 'date',
			'order'   => 'DESC',
		);

		if ( is_numeric( $customer_id_or_email ) ) {
			$args['customer_id'] = absint( $customer_id_or_email );
		} else {
			$args['billing_email'] = sanitize_email( $customer_id_or_email );
		}

		$orders          = wc_get_orders( $args );
		$eligible_orders = array();

		foreach ( $orders as $order ) {
			if ( $this->is_order_eligible( $order ) ) {
				$eligible_orders[] = $order;
			}
		}

		return $eligible_orders;
	}

	/**
	 * Check whether an order already has a pending or approved withdrawal request.
	 *
	 * Rejected requests do not block re-submission.
	 *
	 * @since 1.0.0
	 *
	 * @param int $order_id WooCommerce order ID.
	 * @return bool
	 */
	private function has_active_request( $order_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'wbte_ewb_withdrawals';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table from $wpdb->prefix.
		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE order_id = %d AND status IN ('pending', 'approved')", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$order_id
			)
		);

		return $count > 0;
	}
}
