<?php
/**
 * Delete withdrawal data when a WooCommerce order is permanently removed.
 *
 * @package Wbte_Eu_Withdrawal_Button
 * @since   1.0.3
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Wbte_Ewb_Order_Cleanup
 *
 * @since 1.0.3
 */
class Wbte_Ewb_Order_Cleanup {

	/**
	 * Withdrawal request repository.
	 *
	 * @var Wbte_Ewb_Request_Repository
	 */
	private $repository;

	/**
	 * Pending guest withdrawal repository.
	 *
	 * @var Wbte_Ewb_Pending_Request_Repository
	 */
	private $pending_repository;

	/**
	 * Constructor.
	 *
	 * @since 1.0.3
	 *
	 * @param Wbte_Ewb_Request_Repository         $repository         Request repository.
	 * @param Wbte_Ewb_Pending_Request_Repository $pending_repository Pending request repository.
	 */
	public function __construct( Wbte_Ewb_Request_Repository $repository, Wbte_Ewb_Pending_Request_Repository $pending_repository ) {
		$this->repository         = $repository;
		$this->pending_repository = $pending_repository;

		add_action( 'woocommerce_before_delete_order', array( $this, 'cleanup_for_order' ), 10, 1 );
		add_action( 'before_delete_post', array( $this, 'cleanup_for_legacy_order_post' ), 10, 1 );
	}

	/**
	 * Delete withdrawal data before a WooCommerce order is permanently deleted.
	 *
	 * @since 1.0.3
	 *
	 * @param \WC_Order $order WooCommerce order.
	 * @return void
	 */
	public function cleanup_for_order( $order ) {
		if ( ! $order instanceof \WC_Order ) {
			return;
		}

		$this->delete_withdrawals_for_order( $order );
	}

	/**
	 * Delete withdrawal data when a legacy shop_order post is permanently deleted.
	 *
	 * @since 1.0.3
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function cleanup_for_legacy_order_post( $post_id ) {
		if ( ! function_exists( 'wc_get_order' ) ) {
			return;
		}

		$order = wc_get_order( $post_id );

		if ( ! $order instanceof \WC_Order ) {
			return;
		}

		$this->delete_withdrawals_for_order( $order );
	}

	/**
	 * Remove all withdrawal requests, logs, and pending verifications for an order.
	 *
	 * @since 1.0.3
	 *
	 * @param \WC_Order $order WooCommerce order.
	 * @return void
	 */
	private function delete_withdrawals_for_order( $order ) {
		$order_id       = $order->get_id();
		$order_numbers    = array();
		$display_number = (string) $order->get_order_number();

		if ( '' !== $display_number ) {
			$order_numbers[] = $display_number;
		}

		foreach ( $this->repository->find_by_order( $order_id ) as $request ) {
			if ( ! empty( $request->order_number ) ) {
				$order_numbers[] = (string) $request->order_number;
			}
		}

		$this->repository->delete_by_order( $order_id );

		$order_numbers = array_unique( array_filter( $order_numbers ) );

		foreach ( $order_numbers as $order_number ) {
			$this->pending_repository->delete_by_order_number( $order_number );
		}
	}
}
