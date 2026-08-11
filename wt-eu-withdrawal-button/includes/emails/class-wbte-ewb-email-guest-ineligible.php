<?php
/**
 * Customer email: Guest order ineligible for withdrawal.
 *
 * Sent when a guest submits a withdrawal request for a valid order
 * that is no longer eligible (expired period, already withdrawn, etc.).
 *
 * @package Wbte_Eu_Withdrawal_Button
 * @since   1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Wbte_Ewb_Email_Guest_Ineligible
 *
 * @since 1.1.0
 */
class Wbte_Ewb_Email_Guest_Ineligible extends WC_Email {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id             = 'wbte_ewb_guest_ineligible';
		$this->customer_email = true;
		$this->title          = __( 'Guest Order Not Eligible', 'wt-eu-withdrawal-button' );
		$this->description    = __( 'Sent to guest customers when their order is not eligible for withdrawal.', 'wt-eu-withdrawal-button' );
		$this->template_html  = 'emails/customer-guest-ineligible.php';
		$this->template_plain = 'emails/plain/customer-guest-ineligible.php';
		$this->template_base  = WBTE_EWB_PLUGIN_DIR . 'templates/';
		$this->placeholders   = array(
			'{order_number}' => '',
			'{site_title}'   => $this->get_blogname(),
		);

		// Prevent duplicate hooks when multiple plugin copies instantiate this class.
		if ( ! has_action( 'wbte_ewb_guest_order_ineligible_notification' ) ) {
			add_action( 'wbte_ewb_guest_order_ineligible_notification', array( $this, 'trigger' ), 10, 3 );
		}

		parent::__construct();
	}

	/**
	 * Default subject.
	 *
	 * @return string
	 */
	public function get_default_subject() {
		return __( '[{site_title}]: Your order #{order_number} is not eligible for withdrawal', 'wt-eu-withdrawal-button' );
	}

	/**
	 * Default heading.
	 *
	 * @return string
	 */
	public function get_default_heading() {
		return __( 'Order not eligible for withdrawal', 'wt-eu-withdrawal-button' );
	}

	/**
	 * Trigger the email.
	 *
	 * @param WC_Order $order        The order.
	 * @param string   $email        Guest billing email.
	 * @param string   $order_number Customer-facing order number.
	 * @return void
	 */
	public function trigger( $order, $email, $order_number ) {
		if ( ! $order instanceof WC_Order ) {
			return;
		}

		$this->object    = $order;
		$this->recipient = sanitize_email( $email );
		$this->placeholders['{order_number}'] = $order_number;

		// WPML language switch.
		$order_lang    = $order->get_meta( 'wpml_language' );
		$switched_lang = false;
		if ( $order_lang ) {
			do_action( 'wpml_switch_language', $order_lang ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
			$switched_lang = true;
		}

		$this->setup_locale();

		if ( $this->is_enabled() && $this->get_recipient() ) {
			$this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
		}

		$this->restore_locale();

		if ( $switched_lang ) {
			do_action( 'wpml_switch_language', null ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		}
	}

	/**
	 * HTML content.
	 *
	 * @return string
	 */
	public function get_content_html() {
		return wc_get_template_html(
			$this->template_html,
			array(
				'order'         => $this->object,
				'email_heading' => $this->get_heading(),
				'sent_to_admin' => false,
				'plain_text'    => false,
				'email'         => $this,
			),
			'',
			$this->template_base
		);
	}

	/**
	 * Plain text content.
	 *
	 * @return string
	 */
	public function get_content_plain() {
		return wc_get_template_html(
			$this->template_plain,
			array(
				'order'         => $this->object,
				'email_heading' => $this->get_heading(),
				'sent_to_admin' => false,
				'plain_text'    => true,
				'email'         => $this,
			),
			'',
			$this->template_base
		);
	}
}
