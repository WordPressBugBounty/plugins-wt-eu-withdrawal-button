<?php
/**
 * Custom order statuses for withdrawal workflow.
 *
 * @package Wbte_Eu_Withdrawal_Button
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Wbte_Ewb_Post_Types
 *
 * Registers custom WooCommerce order statuses and exposes
 * them in the admin order-list bulk-action dropdown.
 *
 * @since 1.0.0
 */
class Wbte_Ewb_Post_Types {

	/**
	 * Custom order statuses defined by this plugin.
	 *
	 * @since 1.0.0
	 * @var array<string, array<string, mixed>>
	 */
	private $statuses = array();

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		// Statuses are initialised lazily in get_statuses() to avoid
		// triggering translation functions before the 'init' hook.
	}

	/**
	 * Return the custom status definitions, initialising them on first call.
	 *
	 * This must not be called before the 'init' hook so that
	 * translation functions work correctly (WP 6.7+).
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function get_statuses() {
		if ( empty( $this->statuses ) ) {
			$this->statuses = array(
				'wc-pending-wdraw' => array(
					'label'                     => _x( 'Pending Withdrawal', 'Order status', 'wt-eu-withdrawal-button' ),
					'public'                    => true,
					'exclude_from_search'       => false,
					'show_in_admin_all_list'    => true,
					'show_in_admin_status_list' => true,
					/* translators: %s: number of orders */
					'label_count'               => _n_noop(
						'Pending Withdrawal <span class="count">(%s)</span>',
						'Pending Withdrawal <span class="count">(%s)</span>',
						'wt-eu-withdrawal-button'
					),
				),
				'wc-withdrawn'          => array(
					'label'                     => _x( 'Withdrawn', 'Order status', 'wt-eu-withdrawal-button' ),
					'public'                    => true,
					'exclude_from_search'       => false,
					'show_in_admin_all_list'    => true,
					'show_in_admin_status_list' => true,
					/* translators: %s: number of orders */
					'label_count'               => _n_noop(
						'Withdrawn <span class="count">(%s)</span>',
						'Withdrawn <span class="count">(%s)</span>',
						'wt-eu-withdrawal-button'
					),
				),
			);
		}

		return $this->statuses;
	}

	/**
	 * Register custom post statuses for legacy (CPT) order storage.
	 *
	 * Hooked to `init`.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_post_statuses() {
		foreach ( $this->get_statuses() as $status => $args ) {
			register_post_status( $status, $args );
		}
	}

	/**
	 * Append custom statuses to the WooCommerce order-status list.
	 *
	 * Hooked to `wc_order_statuses`.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, string> $order_statuses Existing order statuses.
	 * @return array<string, string> Modified order statuses.
	 */
	public function add_order_statuses( $order_statuses ) {
		foreach ( $this->get_statuses() as $status => $args ) {
			if ( ! isset( $order_statuses[ $status ] ) ) {
				$order_statuses[ $status ] = $args['label'];
			}
		}

		return $order_statuses;
	}

	/**
	 * Add custom statuses to the orders-list bulk-action dropdown.
	 *
	 * Hooked to `bulk_actions-edit-shop_order` (legacy CPT) and
	 * `bulk_actions-woocommerce_page_wc-orders` (HPOS).
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, string> $actions Existing bulk actions.
	 * @return array<string, string> Modified bulk actions.
	 */
	public function add_bulk_actions( $actions ) {
		foreach ( $this->get_statuses() as $status => $args ) {
			// Bulk action keys use the status without the "wc-" prefix.
			$action_key = 'mark_' . substr( $status, 3 );

			$actions[ $action_key ] = sprintf(
				// translators: %s: order status label (e.g. "Withdrawal Requested").
				__( 'Change status to %s', 'wt-eu-withdrawal-button' ),
				$args['label']
			);
		}

		return $actions;
	}
}
