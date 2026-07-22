<?php
/**
 * Email preview support for WooCommerce email customization screens.
 *
 * @package Wbte_Eu_Withdrawal_Button
 * @since   1.0.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Wbte_Ewb_Email_Preview
 *
 * @since 1.0.1
 */
class Wbte_Ewb_Email_Preview {

	/**
	 * Plugin email IDs that need preview data.
	 *
	 * @var string[]
	 */
	const WT_EWB_EMAIL_IDS = array(
		'wbte_ewb_request_submitted_customer',
		'wbte_ewb_request_submitted_admin',
		'wbte_ewb_request_approved',
		'wbte_ewb_request_rejected',
		'wbte_ewb_guest_verification',
	);

	/**
	 * Register preview hooks.
	 *
	 * @since 1.0.1
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'woocommerce_prepare_email_for_preview', array( __CLASS__, 'prepare_email_for_preview' ) );
		add_filter( 'woocommerce_email_preview_placeholders', array( __CLASS__, 'add_preview_placeholders' ), 10, 3 );
	}

	/**
	 * Populate plugin-specific email properties for preview rendering.
	 *
	 * @since 1.0.1
	 *
	 * @param WC_Email $email Email instance.
	 * @return WC_Email
	 */
	public static function prepare_email_for_preview( $email ) {
		if ( ! ( $email instanceof WC_Email ) || ! in_array( $email->id, self::WT_EWB_EMAIL_IDS, true ) ) {
			return $email;
		}

		if ( 'wbte_ewb_guest_verification' === $email->id ) {
			self::prepare_guest_verification_email( $email );
			return $email;
		}

		self::prepare_request_email( $email );

		return $email;
	}

	/**
	 * Add withdrawal-specific placeholders for email preview subjects.
	 *
	 * @since 1.0.1
	 *
	 * @param array         $placeholders Existing placeholders.
	 * @param string        $email_type   Email class name (WooCommerce preview type).
	 * @param WC_Order|null $email_object Preview order. Optional before WooCommerce 9.9.0.
	 * @return array
	 */
	public static function add_preview_placeholders( $placeholders, $email_type, $email_object = null ) {
		if ( ! self::is_plugin_preview_email_type( $email_type ) ) {
			return $placeholders;
		}

		if ( ! ( $email_object instanceof WC_Order ) ) {
			$email_object = self::get_preview_order_from_email_type( $email_type );
		}

		if ( ! ( $email_object instanceof WC_Order ) ) {
			return $placeholders;
		}

		$placeholders['{customer_name}'] = $email_object->get_formatted_billing_full_name();
		$placeholders['{request_date}']  = Wbte_Ewb_Request::format_datetime( gmdate( 'Y-m-d H:i:s' ) );
		$placeholders['{request_type}']  = __( 'Full', 'wt-eu-withdrawal-button' );

		return $placeholders;
	}

	/**
	 * Whether the preview email type belongs to this plugin.
	 *
	 * @since 1.0.6
	 *
	 * @param string $email_type Email class name from WooCommerce preview.
	 * @return bool
	 */
	private static function is_plugin_preview_email_type( $email_type ) {
		if ( ! is_string( $email_type ) || ! class_exists( $email_type ) || ! is_subclass_of( $email_type, 'WC_Email' ) ) {
			return false;
		}

		if ( ! function_exists( 'WC' ) || ! WC()->mailer() ) {
			return false;
		}

		foreach ( WC()->mailer()->get_emails() as $email ) {
			if ( get_class( $email ) === $email_type && in_array( $email->id, self::WT_EWB_EMAIL_IDS, true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Resolve the preview order for older WooCommerce versions.
	 *
	 * WooCommerce 9.9.0+ passes the preview object to the placeholders filter.
	 * On earlier versions, read it from the mailer email instance instead.
	 *
	 * @since 1.0.6
	 *
	 * @param string $email_type Email class name from WooCommerce preview.
	 * @return WC_Order|null
	 */
	private static function get_preview_order_from_email_type( $email_type ) {
		if ( ! function_exists( 'WC' ) || ! WC()->mailer() ) {
			return null;
		}

		foreach ( WC()->mailer()->get_emails() as $email ) {
			if ( get_class( $email ) === $email_type && $email->object instanceof WC_Order ) {
				return $email->object;
			}
		}

		return null;
	}

	/**
	 * Prepare guest verification email preview data.
	 *
	 * @since 1.0.1
	 *
	 * @param WC_Email $email Email instance.
	 * @return void
	 */
	private static function prepare_guest_verification_email( $email ) {
		if ( $email->pending instanceof Wbte_Ewb_Pending_Request ) {
			return;
		}

		$pending                 = new Wbte_Ewb_Pending_Request();
		$pending->id             = 1;
		$pending->order_number   = '12345';
		$pending->customer_email = 'customer@example.com';
		$pending->reason         = __( 'I would like to return this order.', 'wt-eu-withdrawal-button' );
		$pending->verify_token   = 'preview-token';

		$email->pending          = $pending;
		$email->verification_url = home_url( '/?wbte_ewb_verify=preview' );
		$email->placeholders['{order_number}'] = $pending->order_number;
	}

	/**
	 * Prepare request-based email preview data.
	 *
	 * @since 1.0.1
	 *
	 * @param WC_Email $email Email instance.
	 * @return void
	 */
	private static function prepare_request_email( $email ) {
		$order = $email->object instanceof WC_Order ? $email->object : null;

		if ( ! $order ) {
			return;
		}

		if ( ! ( $email->request instanceof Wbte_Ewb_Request ) ) {
			$email->request = self::get_preview_request( $email->id, $order );
		}

		if ( ! ( $email->order instanceof WC_Order ) ) {
			$email->order = $order;
		}

		if ( 'wbte_ewb_request_rejected' === $email->id && '' === $email->reject_note ) {
			$email->reject_note = __( 'This request cannot be approved because the withdrawal period has expired.', 'wt-eu-withdrawal-button' );
		}

		$email->placeholders['{order_number}']  = $order->get_order_number();
		$email->placeholders['{customer_name}'] = $order->get_formatted_billing_full_name();
		$email->placeholders['{request_date}']  = $email->request->get_created_at_formatted();
		$email->placeholders['{request_type}']  = __( 'Full', 'wt-eu-withdrawal-button' );
	}

	/**
	 * Build a sample withdrawal request for email previews.
	 *
	 * @since 1.0.1
	 *
	 * @param string   $email_id Email type ID.
	 * @param WC_Order $order    Preview order.
	 * @return Wbte_Ewb_Request
	 */
	private static function get_preview_request( $email_id, $order ) {
		$request                   = new Wbte_Ewb_Request();
		$request->id               = 1;
		$request->order_id         = $order->get_id();
		$request->order_number     = $order->get_order_number();
		$request->customer_email   = $order->get_billing_email();
		$request->customer_user_id = 0;
		$request->request_type     = 'full';
		$request->reason           = __( 'I would like to return this order.', 'wt-eu-withdrawal-button' );
		$request->created_at       = gmdate( 'Y-m-d H:i:s' );
		$request->processed_at     = gmdate( 'Y-m-d H:i:s' );
		$request->items_json       = wp_json_encode(
			array(
				array(
					'name' => __( 'Sample product', 'wt-eu-withdrawal-button' ),
					'qty'  => 1,
				),
			)
		);

		switch ( $email_id ) {
			case 'wbte_ewb_request_approved':
				$request->status = 'approved';
				break;
			case 'wbte_ewb_request_rejected':
				$request->status = 'rejected';
				$request->meta_json = wp_json_encode(
					array(
						'reject_reason' => __( 'This request cannot be approved because the withdrawal period has expired.', 'wt-eu-withdrawal-button' ),
					)
				);
				break;
			default:
				$request->status = 'pending';
				break;
		}

		return $request;
	}
}
