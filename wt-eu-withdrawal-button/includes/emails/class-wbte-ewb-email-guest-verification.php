<?php
/**
 * Customer email: Guest withdrawal verification.
 *
 * @package Wbte_Eu_Withdrawal_Button
 * @since   1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Wbte_Ewb_Email_Guest_Verification
 *
 * @since 1.1.0
 */
class Wbte_Ewb_Email_Guest_Verification extends WC_Email {

	/**
	 * Pending guest request.
	 *
	 * @var Wbte_Ewb_Pending_Request|null
	 */
	public $pending = null;

	/**
	 * Verification URL.
	 *
	 * @var string
	 */
	public $verification_url = '';

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id             = 'wbte_ewb_guest_verification';
		$this->customer_email = true;
		$this->title          = __( 'Guest Withdrawal Verification', 'wt-eu-withdrawal-button' );
		$this->description    = __( 'Sent to guest customers to verify their withdrawal request before it is created.', 'wt-eu-withdrawal-button' );
		$this->template_html  = 'emails/customer-guest-verification.php';
		$this->template_plain = 'emails/plain/customer-guest-verification.php';
		$this->template_base  = WBTE_EWB_PLUGIN_DIR . 'templates/';
		$this->placeholders   = array(
			'{order_number}' => '',
			'{site_title}'   => $this->get_blogname(),
		);

		add_action( 'wbte_ewb_guest_verification_notification', array( $this, 'trigger' ), 10, 1 );

		parent::__construct();
	}

	/**
	 * Default subject.
	 *
	 * @return string
	 */
	public function get_default_subject() {
		return __( '[{site_title}]: Verify your withdrawal request for order #{order_number}', 'wt-eu-withdrawal-button' );
	}

	/**
	 * Default heading.
	 *
	 * @return string
	 */
	public function get_default_heading() {
		return __( 'Verify your withdrawal request', 'wt-eu-withdrawal-button' );
	}

	/**
	 * Trigger the email.
	 *
	 * @param Wbte_Ewb_Pending_Request $pending Pending request.
	 * @return void
	 */
	public function trigger( $pending ) {
		$this->setup_locale();

		if ( ! ( $pending instanceof Wbte_Ewb_Pending_Request ) ) {
			$this->restore_locale();
			return;
		}

		$this->pending          = $pending;
		$this->recipient        = $pending->customer_email;
		$this->placeholders['{order_number}'] = $pending->order_number;

		$this->verification_url = $this->build_verification_url( $pending );

		if ( $this->is_enabled() && $this->get_recipient() ) {
			$this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
		}

		$this->restore_locale();
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
				'pending'          => $this->pending,
				'verification_url' => $this->verification_url,
				'email_heading'    => $this->get_heading(),
				'sent_to_admin'    => false,
				'plain_text'       => false,
				'email'            => $this,
			),
			Wbte_Ewb_Emails::TEMPLATE_PATH,
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
				'pending'          => $this->pending,
				'verification_url' => $this->verification_url,
				'email_heading'    => $this->get_heading(),
				'sent_to_admin'    => false,
				'plain_text'       => true,
				'email'            => $this,
			),
			Wbte_Ewb_Emails::TEMPLATE_PATH,
			$this->template_base
		);
	}

	/**
	 * Build the verification URL for a pending request.
	 *
	 * @param Wbte_Ewb_Pending_Request $pending Pending request.
	 * @return string
	 */
	private function build_verification_url( $pending ) {
		if ( function_exists( 'wbte_ewb' ) ) {
			$guest_service = wbte_ewb()->get( 'guest_withdrawal' );
			if ( $guest_service ) {
				return $guest_service->get_verification_url( $pending );
			}
		}

		if ( empty( $pending->verify_token ) ) {
			return '';
		}

		$base = Wbte_Ewb_Settings::get_withdrawal_page_url();

		if ( ! $base ) {
			$base = home_url( '/' );
		}

		return add_query_arg(
			array(
				'wbte_ewb_verify' => rawurlencode( $pending->verify_token ),
			),
			$base
		);
	}
}
