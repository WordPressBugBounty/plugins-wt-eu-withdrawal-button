<?php
/**
 * Withdrawal form wrapper template.
 *
 * This template is loaded via wc_get_template() so themes can
 * override it by placing a copy in:
 *   yourtheme/wbte-eu-withdrawal-button/withdrawal-form.php
 *
 * @package Wbte_Eu_Withdrawal_Button
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fires before the withdrawal form is rendered.
 *
 * @since 1.0.0
 */
do_action( 'wbte_ewb_before_withdrawal_form' );

if ( class_exists( 'Wbte_Ewb_Guest_Verification' ) ) {
	$wbte_ewb_verified_view = Wbte_Ewb_Guest_Verification::render_verified_view();

	if ( null !== $wbte_ewb_verified_view ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in template.
		echo $wbte_ewb_verified_view;

		do_action( 'wbte_ewb_after_withdrawal_form' );
		return;
	}
}

if ( is_user_logged_in() ) {

	// Build the list of eligible orders for the current customer.
	$wbte_ewb_current_user         = wp_get_current_user();
	$wbte_ewb_preselected_order_id = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

	if ( ! class_exists( 'Wbte_Ewb_Eligibility' ) ) {
		require_once WBTE_EWB_PLUGIN_DIR . 'includes/core/class-wbte-ewb-eligibility.php';
	}

	$wbte_ewb_eligibility     = new Wbte_Ewb_Eligibility();
	$wbte_ewb_eligible_orders = $wbte_ewb_eligibility->get_eligible_orders_for_customer( $wbte_ewb_current_user->ID );

	/**
	 * Filters the list of eligible orders shown in the withdrawal form.
	 *
	 * @since 1.0.0
	 *
	 * @param WC_Order[] $wbte_ewb_eligible_orders       Orders eligible for withdrawal.
	 * @param int        $wbte_ewb_preselected_order_id  Pre-selected order ID from query string.
	 */
	$wbte_ewb_eligible_orders = apply_filters( 'wbte_ewb_eligible_orders', $wbte_ewb_eligible_orders, $wbte_ewb_preselected_order_id );

	include WBTE_EWB_PLUGIN_DIR . 'includes/frontend/views/form-logged-in.php';

} elseif ( class_exists( 'Wbte_Ewb_Guest_Verification' ) && Wbte_Ewb_Guest_Verification::is_verified_form_view() ) {
	$wbte_ewb_verified_pending = Wbte_Ewb_Guest_Verification::get_verified_pending_request();

	if ( is_wp_error( $wbte_ewb_verified_pending ) ) {
		$wbte_ewb_notice_message  = $wbte_ewb_verified_pending->get_error_message();
		$wbte_ewb_notice_is_error = true;
		include WBTE_EWB_PLUGIN_DIR . 'includes/frontend/views/form-guest.php';
	} else {
		$wbte_ewb_guest_service = function_exists( 'wbte_ewb' ) ? wbte_ewb()->get( 'guest_withdrawal' ) : null;
		$wbte_ewb_verified_order  = $wbte_ewb_guest_service instanceof Wbte_Ewb_Guest_Withdrawal_Service
			? $wbte_ewb_guest_service->resolve_order_by_number( $wbte_ewb_verified_pending->order_number )
			: false;

		if ( ! $wbte_ewb_verified_order ) {
			$wbte_ewb_notice_message  = __( 'No order found matching the provided order number.', 'wt-eu-withdrawal-button' );
			$wbte_ewb_notice_is_error = true;
			include WBTE_EWB_PLUGIN_DIR . 'includes/frontend/views/form-guest.php';
		} else {
			include WBTE_EWB_PLUGIN_DIR . 'includes/frontend/views/form-guest-verified.php';
		}
	}

} else {

	include WBTE_EWB_PLUGIN_DIR . 'includes/frontend/views/form-guest.php';

}

/**
 * Fires after the withdrawal form is rendered.
 *
 * @since 1.0.0
 */
do_action( 'wbte_ewb_after_withdrawal_form' );
