<?php
/**
 * Admin asset enqueuing.
 *
 * Registers and enqueues JavaScript and CSS assets for the
 * React dashboard page and the WooCommerce order edit screens.
 *
 * @package Wbte_Eu_Withdrawal_Button
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Wbte_Ewb_Admin_Assets
 *
 * @since 1.0.0
 */
class Wbte_Ewb_Admin_Assets {

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	/**
	 * Enqueue admin scripts and styles based on the current screen.
	 *
	 * @since 1.0.0
	 *
	 * @param string $hook_suffix The current admin page hook suffix.
	 * @return void
	 */
	public function enqueue_scripts( $hook_suffix ) {
		$screen = get_current_screen();

		if ( ! $screen ) {
			return;
		}

		// React dashboard page.
		if ( 'woocommerce_page_wbte-ewb-withdrawals' === $screen->id ) {
			$this->enqueue_dashboard_assets();
		}

		// WooCommerce order edit screens (legacy + HPOS).
		if ( $this->is_order_edit_screen( $screen ) ) {
			$this->enqueue_order_assets();
		}
	}

	/**
	 * Enqueue the React admin dashboard bundle.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function enqueue_dashboard_assets() {
		$asset_file = WBTE_EWB_PLUGIN_DIR . 'react/build/admin-dashboard.asset.php';
		$asset      = file_exists( $asset_file )
			? require $asset_file
			: array(
				'dependencies' => array(),
				'version'      => WBTE_EWB_VERSION,
			);

		wp_enqueue_script(
			'wbte-ewb-admin-dashboard',
			WBTE_EWB_PLUGIN_URL . 'react/build/admin-dashboard.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		// Enqueue CSS files if they exist.
		$css_files = array(
			'admin-dashboard.css',
			'style-admin-dashboard.css',
		);
		foreach ( $css_files as $css_filename ) {
			$css_path = WBTE_EWB_PLUGIN_DIR . 'react/build/' . $css_filename;
			if ( file_exists( $css_path ) ) {
				wp_enqueue_style(
					'wbte-ewb-' . str_replace( '.css', '', $css_filename ),
					WBTE_EWB_PLUGIN_URL . 'react/build/' . $css_filename,
					array( 'wp-components' ),
					$asset['version']
				);
			}
		}

		// Localize script data.
		wp_localize_script(
			'wbte-ewb-admin-dashboard',
			'wbteEwbAdmin',
			array(
				'rest_url'       => esc_url_raw( rest_url( 'wbte-ewb/v1/' ) ),
				'nonce'          => wp_create_nonce( 'wp_rest' ),
				'admin_url'      => esc_url_raw( admin_url() ),
				'plugin_url'     => esc_url_raw( WBTE_EWB_PLUGIN_URL ),
				'version'        => WBTE_EWB_VERSION,
				'settings'       => Wbte_Ewb_Settings::get_all(),
				'order_statuses' => function_exists( 'wc_get_order_statuses' ) ? wc_get_order_statuses() : array(),
				'default_store_email' => Wbte_Ewb_Settings::get_store_email(),
				'i18n'           => array(
					'pending'          => __( 'Pending', 'wt-eu-withdrawal-button' ),
					'approved'         => __( 'Approved', 'wt-eu-withdrawal-button' ),
					'rejected'         => __( 'Rejected', 'wt-eu-withdrawal-button' ),
					'approve'          => __( 'Approve', 'wt-eu-withdrawal-button' ),
					'reject'           => __( 'Reject', 'wt-eu-withdrawal-button' ),
					'full_withdrawal'  => __( 'Full Withdrawal', 'wt-eu-withdrawal-button' ),
					'partial'          => __( 'Partial Withdrawal', 'wt-eu-withdrawal-button' ),
					'no_requests'      => __( 'No withdrawal requests found.', 'wt-eu-withdrawal-button' ),
					'loading'          => __( 'Loading…', 'wt-eu-withdrawal-button' ),
					'error'            => __( 'An error occurred. Please try again.', 'wt-eu-withdrawal-button' ),
					'confirm_approve'  => __( 'Are you sure you want to approve this withdrawal request?', 'wt-eu-withdrawal-button' ),
					'confirm_reject'   => __( 'Are you sure you want to reject this withdrawal request?', 'wt-eu-withdrawal-button' ),
				),
			)
		);

		// Script translations for JS i18n (WPML-aware locale + inline fallback).
		wp_set_script_translations(
			'wbte-ewb-admin-dashboard',
			'wt-eu-withdrawal-button',
			WBTE_EWB_PLUGIN_DIR . 'languages'
		);

		Wbte_Ewb_Script_Translations::enqueue_inline_locale_data( 'wbte-ewb-admin-dashboard' );
	}

	/**
	 * Enqueue assets for WooCommerce order edit screens.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function enqueue_order_assets() {
		wp_enqueue_style(
			'wbte-ewb-admin',
			WBTE_EWB_PLUGIN_URL . 'assets/css/wbte-ewb-admin.css',
			array(),
			WBTE_EWB_VERSION
		);

		wp_enqueue_script(
			'wbte-ewb-admin-order',
			WBTE_EWB_PLUGIN_URL . 'assets/js/wbte-ewb-admin-order.js',
			array( 'jquery' ),
			WBTE_EWB_VERSION,
			true
		);

		wp_localize_script(
			'wbte-ewb-admin-order',
			'wbte_ewb_admin_order_params',
			array(
				'rest_url' => esc_url_raw( rest_url( 'wbte-ewb/v1/' ) ),
				'nonce'    => wp_create_nonce( 'wp_rest' ),
				'i18n'     => array(
					'processing'           => __( 'Processing…', 'wt-eu-withdrawal-button' ),
					'approve'              => __( 'Approve', 'wt-eu-withdrawal-button' ),
					'reject'               => __( 'Reject', 'wt-eu-withdrawal-button' ),
					'confirm_approve'      => __( 'Are you sure you want to approve this withdrawal request?', 'wt-eu-withdrawal-button' ),
					'confirm_reject'       => __( 'Are you sure you want to reject this withdrawal request?', 'wt-eu-withdrawal-button' ),
					'reject_note_prompt'   => __( 'Please enter a reason for rejecting this withdrawal request:', 'wt-eu-withdrawal-button' ),
					'reject_note_required' => __( 'A reason is required when rejecting a request.', 'wt-eu-withdrawal-button' ),
					'success'              => __( 'Request updated successfully.', 'wt-eu-withdrawal-button' ),
					'error'                => __( 'An error occurred. Please try again.', 'wt-eu-withdrawal-button' ),
				),
			)
		);
	}

	/**
	 * Check if the current screen is a WooCommerce order edit screen.
	 *
	 * Supports both legacy (post type) and HPOS order screens.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_Screen $screen Current admin screen object.
	 * @return bool
	 */
	private function is_order_edit_screen( $screen ) {
		// Legacy CPT order edit screen.
		if ( 'shop_order' === $screen->id ) {
			return true;
		}

		// Legacy CPT orders list screen.
		if ( 'edit-shop_order' === $screen->id ) {
			return true;
		}

		// HPOS order edit / list screen.
		if ( 'woocommerce_page_wc-orders' === $screen->id ) {
			return true;
		}

		return false;
	}
}
