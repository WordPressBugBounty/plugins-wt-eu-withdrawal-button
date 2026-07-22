<?php
/**
 * Frontend controller.
 *
 * Bootstraps all front-end subsystems: asset loading,
 * form handler, My Account integration, and optional footer link.
 *
 * @package Wbte_Eu_Withdrawal_Button
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Wbte_Ewb_Frontend
 *
 * @since 1.0.0
 */
class Wbte_Ewb_Frontend {

	/**
	 * Form handler instance.
	 *
	 * @since 1.0.0
	 * @var Wbte_Ewb_Form_Handler
	 */
	private $form_handler;

	/**
	 * My Account integration instance.
	 *
	 * @since 1.0.0
	 * @var Wbte_Ewb_My_Account
	 */
	private $my_account;

	/**
	 * Constructor.
	 *
	 * Loads dependencies and registers front-end hooks.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->load_dependencies();

		$this->form_handler = new Wbte_Ewb_Form_Handler();
		$this->my_account   = new Wbte_Ewb_My_Account();

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );

		$this->maybe_add_footer_link();
	}

	/**
	 * Load required front-end class files.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function load_dependencies() {
		$dir = WBTE_EWB_PLUGIN_DIR . 'includes/frontend/';

		require_once $dir . 'class-wbte-ewb-form-handler.php';
		require_once $dir . 'class-wbte-ewb-my-account.php';
	}

	/**
	 * Enqueue front-end CSS and JS on relevant pages.
	 *
	 * Only loads assets on the configured withdrawal page or
	 * WooCommerce pages (cart, checkout, my-account, etc.).
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		if ( ! $this->should_enqueue() ) {
			return;
		}

		wp_enqueue_style(
			'wbte-ewb-frontend',
			WBTE_EWB_PLUGIN_URL . 'assets/css/wbte-ewb-frontend.css',
			array(),
			WBTE_EWB_VERSION
		);

		wp_enqueue_script(
			'wbte-ewb-frontend',
			WBTE_EWB_PLUGIN_URL . 'assets/js/wbte-ewb-frontend.js',
			array( 'jquery' ),
			WBTE_EWB_VERSION,
			true
		);

		wp_localize_script(
			'wbte-ewb-frontend',
			'wbte_ewb_frontend_params',
			array(
				'ajax_url'                  => admin_url( 'admin-ajax.php' ),
				'rest_url'                  => esc_url_raw( rest_url( 'wbte-ewb/v1/' ) ),
				'nonce'                     => wp_create_nonce( 'wp_rest' ),
				'currency_symbol'           => html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' ),
				'currency_position'         => get_option( 'woocommerce_currency_pos', 'left' ),
				'currency_decimals'         => absint( get_option( 'woocommerce_price_num_decimals', 2 ) ),
				'currency_decimal_sep'      => wc_get_price_decimal_separator(),
				'currency_thousand_sep'     => wc_get_price_thousand_separator(),
				'allow_partial_withdrawals' => Wbte_Ewb_Settings::get( 'allow_partial_withdrawals', 'yes' ),
				'reason_required'           => Wbte_Ewb_Settings::get( 'reason_required', 'no' ),
				'i18n'                      => $this->get_frontend_i18n(),
			)
		);
	}

	/**
	 * Get localized strings for the frontend withdrawal script.
	 *
	 * @since 1.0.5
	 *
	 * @return array<string, string>
	 */
	private function get_frontend_i18n() {
		$i18n = array(
			'loading'              => __( 'Loading...', 'wt-eu-withdrawal-button' ),
			'select_order'         => __( 'Please select an order.', 'wt-eu-withdrawal-button' ),
			'select_items'         => __( 'Please select at least one item.', 'wt-eu-withdrawal-button' ),
			'withdraw_qty'         => __( 'quantity', 'wt-eu-withdrawal-button' ),
			'invalid_item_qty'     => __( 'Please enter a valid quantity for each selected item.', 'wt-eu-withdrawal-button' ),
			'reason_required'      => __( 'Please provide a reason for withdrawal.', 'wt-eu-withdrawal-button' ),
			'submit_error'         => __( 'An error occurred. Please try again.', 'wt-eu-withdrawal-button' ),
			'submit_success'       => __( 'Your withdrawal request has been submitted successfully.', 'wt-eu-withdrawal-button' ),
			'order_required'       => __( 'Please enter your order number.', 'wt-eu-withdrawal-button' ),
			'email_required'       => __( 'Please enter a valid email address.', 'wt-eu-withdrawal-button' ),
			'fetching_items'       => __( 'Fetching eligible items...', 'wt-eu-withdrawal-button' ),
			'no_eligible_items'    => __( 'No eligible items found for this order.', 'wt-eu-withdrawal-button' ),
			'confirm_title'        => __( 'Review your withdrawal request', 'wt-eu-withdrawal-button' ),
			'confirm_intro'        => __( 'Please review the details below. To complete your statutory withdrawal, click Confirm withdrawal.', 'wt-eu-withdrawal-button' ),
			'confirm_withdrawal'   => __( 'Confirm withdrawal', 'wt-eu-withdrawal-button' ),
			'confirm_cancel'       => __( 'Cancel', 'wt-eu-withdrawal-button' ),
			'label_customer'       => __( 'Customer', 'wt-eu-withdrawal-button' ),
			'label_order'          => __( 'Order', 'wt-eu-withdrawal-button' ),
			'label_request_type'   => __( 'Withdrawal type', 'wt-eu-withdrawal-button' ),
			'label_items'          => __( 'Items to withdraw', 'wt-eu-withdrawal-button' ),
			'label_reason'         => __( 'Reason for withdrawal', 'wt-eu-withdrawal-button' ),
			'request_type_full'    => __( 'Full withdrawal', 'wt-eu-withdrawal-button' ),
			'request_type_partial' => __( 'Partial withdrawal', 'wt-eu-withdrawal-button' ),
			'guest_queue_success'  => Wbte_Ewb_Guest_Verification::get_queue_success_message(),
		);

		/**
		 * Filters frontend JavaScript translation strings.
		 *
		 * Use this to customize withdrawal form messages, validation errors,
		 * and the confirmation modal copy (e.g. confirm_title, confirm_withdrawal).
		 *
		 * @since 1.0.5
		 *
		 * @param array<string, string> $i18n Associative array of localized strings for wbte-ewb-frontend.js.
		 */
		$i18n = apply_filters( 'wbte_ewb_frontend_i18n', $i18n );

		/**
		 * Filters withdrawal confirmation modal strings only.
		 *
		 * Merged on top of {@see 'wbte_ewb_frontend_i18n'} for confirm_* and label_* keys.
		 *
		 * @since 1.0.5
		 *
		 * @param array<string, string> $confirm_i18n Confirmation modal strings.
		 * @param array<string, string> $i18n         Full frontend i18n array.
		 */
		$confirm_i18n = apply_filters(
			'wbte_ewb_withdrawal_confirm_i18n',
			array(
				'confirm_title'        => $i18n['confirm_title'],
				'confirm_intro'        => $i18n['confirm_intro'],
				'confirm_withdrawal'   => $i18n['confirm_withdrawal'],
				'confirm_cancel'       => $i18n['confirm_cancel'],
				'label_customer'       => $i18n['label_customer'],
				'label_order'          => $i18n['label_order'],
				'label_request_type'   => $i18n['label_request_type'],
				'label_items'          => $i18n['label_items'],
				'label_reason'         => $i18n['label_reason'],
				'request_type_full'    => $i18n['request_type_full'],
				'request_type_partial' => $i18n['request_type_partial'],
			),
			$i18n
		);

		return array_merge( $i18n, $confirm_i18n );
	}

	/**
	 * Determine whether front-end assets should be enqueued.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	private function should_enqueue() {
		$withdrawal_page_id = Wbte_Ewb_Settings::get_withdrawal_page_id();

		// On the configured withdrawal form page.
		if ( $withdrawal_page_id && is_page( $withdrawal_page_id ) ) {
			return true;
		}

		// On any page with our shortcodes.
		if ( Wbte_Ewb_Shortcodes::post_has_shortcode() ) {
			return true;
		}

		// On any WooCommerce page.
		if ( Wbte_Ewb_Settings::is_woocommerce_page_context() ) {
			return true;
		}

		/**
		 * Filters whether front-end assets should be enqueued.
		 *
		 * @since 1.0.0
		 *
		 * @param bool $enqueue Whether to enqueue.
		 */
		return (bool) apply_filters( 'wbte_ewb_enqueue_frontend_assets', false );
	}

	/**
	 * Conditionally hook the footer link output.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function maybe_add_footer_link() {
		if ( 'yes' === Wbte_Ewb_Settings::get( 'embed_footer_link', 'yes' ) ) {
			add_action( 'wp_footer', array( $this, 'render_footer_link' ) );
		}
	}

	/**
	 * Render the withdrawal page link in the footer.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function render_footer_link() {
		if ( ! Wbte_Ewb_Settings::is_withdrawal_display_allowed() ) {
			return;
		}

		$country = Wbte_Ewb_Customer_Context::get_country_code();
		/**
		 * Filters whether the footer withdrawal link should be rendered.
		 *
		 * @since 1.0.4
		 *
		 * @param bool   $show    Whether to render the footer link.
		 * @param string $country Customer ISO country code, if known.
		 */
		if ( ! apply_filters( 'wbte_ewb_show_footer_withdrawal_link', true, $country ) ) {
			return;
		}

		$url = Wbte_Ewb_Settings::get_withdrawal_page_url();

		if ( ! $url ) {
			return;
		}

		$link_text = Wbte_Ewb_Settings::get_button_label( Wbte_Ewb_Settings::LABEL_FOOTER_LINK );

		printf(
			'<div class="wbte-ewb-footer-link" style="text-align:center;"><a href="%s">%s</a></div>',
			esc_url( $url ),
			esc_html( $link_text )
		);
	}

	/**
	 * Get the form handler instance.
	 *
	 * @since 1.0.0
	 *
	 * @return Wbte_Ewb_Form_Handler
	 */
	public function get_form_handler() {
		return $this->form_handler;
	}

	/**
	 * Get the My Account integration instance.
	 *
	 * @since 1.0.0
	 *
	 * @return Wbte_Ewb_My_Account
	 */
	public function get_my_account() {
		return $this->my_account;
	}
}
