<?php
/**
 * Customer email: Withdrawal request submitted.
 *
 * Sent to the customer when a withdrawal request is submitted.
 *
 * @package Wbte_Eu_Withdrawal_Button
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Wbte_Ewb_Email_Request_Submitted_Customer
 *
 * @since 1.0.0
 */
class Wbte_Ewb_Email_Request_Submitted_Customer extends WC_Email {

	/**
	 * The withdrawal request object.
	 *
	 * @since 1.0.0
	 * @var Wbte_Ewb_Request|null
	 */
	public $request = null;

	/**
	 * The WooCommerce order object.
	 *
	 * @since 1.0.0
	 * @var WC_Order|null
	 */
	public $order = null;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->id             = 'wbte_ewb_request_submitted_customer';
		$this->customer_email = true;
		$this->title          = __( 'Withdrawal Request Submitted', 'wt-eu-withdrawal-button' );
		$this->description    = __( 'Sent to the customer when a withdrawal request is submitted.', 'wt-eu-withdrawal-button' );
		$this->template_html  = 'emails/customer-withdrawal-submitted.php';
		$this->template_plain = 'emails/plain/customer-withdrawal-submitted.php';
		$this->template_base  = WBTE_EWB_PLUGIN_DIR . 'templates/';
		$this->placeholders   = array(
			'{order_number}'  => '',
			'{customer_name}' => '',
			'{request_date}'  => '',
			'{request_type}'  => '',
			'{site_title}'    => $this->get_blogname(),
		);

		// Trigger on request submitted action.
		add_action( 'wbte_ewb_request_submitted_notification', array( $this, 'trigger' ), 10, 2 );

		parent::__construct();
	}

	/**
	 * Get the default email subject.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_default_subject() {
		return __( '[{site_title}]: Withdrawal request for order #{order_number} received', 'wt-eu-withdrawal-button' );
	}

	/**
	 * Get the default email heading.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_default_heading() {
		return __( 'Withdrawal Request Received', 'wt-eu-withdrawal-button' );
	}

	/**
	 * Trigger the email.
	 *
	 * @since 1.0.0
	 *
	 * @param Wbte_Ewb_Request $request The withdrawal request.
	 * @param WC_Order        $order   The WooCommerce order.
	 * @return void
	 */
	public function trigger( $request, $order ) {
		if ( ! ( $request instanceof Wbte_Ewb_Request ) || ! ( $order instanceof WC_Order ) ) {
			return;
		}

		$this->request   = $request;
		$this->order     = $order;
		$this->recipient = $request->customer_email;
		$this->object    = $order;

		// Switch to the order's language for email translation (WPML, TranslatePress, Polylang).
		$order_lang    = Wbte_Ewb_Multilingual::get_order_language( $order );
		$switched_lang = Wbte_Ewb_Multilingual::switch_email_language( $order_lang );

		$this->setup_locale();

		$this->placeholders['{order_number}']  = $order->get_order_number();
		$this->placeholders['{customer_name}'] = $order->get_formatted_billing_full_name();
		$this->placeholders['{request_date}']  = $request->get_created_at_formatted();
		$this->placeholders['{request_type}']  = $request->get_request_type_label();

		if ( $this->is_enabled() && $this->get_recipient() ) {
			$this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
		}

		$this->restore_locale();

		if ( $switched_lang ) {
			Wbte_Ewb_Multilingual::restore_email_language();
		}
	}

	/**
	 * Get the HTML email content.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_content_html() {
		return wc_get_template_html(
			$this->template_html,
			array(
				'request'       => $this->request,
				'order'         => $this->order,
				'email_heading' => $this->get_heading(),
				'sent_to_admin' => false,
				'plain_text'    => false,
				'email'         => $this,
			),
			Wbte_Ewb_Emails::TEMPLATE_PATH,
			$this->template_base
		);
	}

	/**
	 * Get the plain-text email content.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_content_plain() {
		return wc_get_template_html(
			$this->template_plain,
			array(
				'request'       => $this->request,
				'order'         => $this->order,
				'email_heading' => $this->get_heading(),
				'sent_to_admin' => false,
				'plain_text'    => true,
				'email'         => $this,
			),
			Wbte_Ewb_Emails::TEMPLATE_PATH,
			$this->template_base
		);
	}

	/**
	 * Initialise form fields for the WooCommerce email settings screen.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function init_form_fields() {
		/* translators: %s: list of available placeholder tags. */
		$placeholder_text = sprintf( __( 'Available placeholders: %s', 'wt-eu-withdrawal-button' ), '<code>' . esc_html( implode( '</code>, <code>', array_keys( $this->placeholders ) ) ) . '</code>' );

		$this->form_fields = array(
			'enabled'    => array(
				'title'   => __( 'Enable/Disable', 'wt-eu-withdrawal-button' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable this email notification', 'wt-eu-withdrawal-button' ),
				'default' => 'yes',
			),
			'subject'    => array(
				'title'       => __( 'Subject', 'wt-eu-withdrawal-button' ),
				'type'        => 'text',
				'desc_tip'    => true,
				'description' => $placeholder_text,
				'placeholder' => $this->get_default_subject(),
				'default'     => '',
			),
			'heading'    => array(
				'title'       => __( 'Email heading', 'wt-eu-withdrawal-button' ),
				'type'        => 'text',
				'desc_tip'    => true,
				'description' => $placeholder_text,
				'placeholder' => $this->get_default_heading(),
				'default'     => '',
			),
			'email_type' => array(
				'title'       => __( 'Email type', 'wt-eu-withdrawal-button' ),
				'type'        => 'select',
				'description' => __( 'Choose which format of email to send.', 'wt-eu-withdrawal-button' ),
				'default'     => 'html',
				'class'       => 'email_type wc-enhanced-select',
				'options'     => $this->get_email_type_options(),
				'desc_tip'    => true,
			),
		);
	}
}
