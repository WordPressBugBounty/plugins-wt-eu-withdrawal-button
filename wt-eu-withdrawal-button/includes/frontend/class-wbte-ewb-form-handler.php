<?php
/**
 * Non-JS fallback form handler.
 *
 * Catches the withdrawal form POST submission on template_redirect,
 * validates, sanitizes, and delegates to the request service.
 *
 * @package Wbte_Eu_Withdrawal_Button
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Wbte_Ewb_Form_Handler
 *
 * @since 1.0.0
 */
class Wbte_Ewb_Form_Handler {

	/**
	 * Validation errors for the current request.
	 *
	 * @since 1.0.0
	 * @var WP_Error
	 */
	private static $errors;

	/**
	 * Success message after a successful submission.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private static $success_message = '';

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		self::$errors = new WP_Error();

		add_action( 'template_redirect', array( $this, 'handle_form_submission' ) );
	}

	/**
	 * Process the withdrawal form POST submission.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function handle_form_submission() {
		if ( 'POST' !== strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ?? '' ) ) ) ) {
			return;
		}

		if ( ! isset( $_POST['wbte_ewb_action'] ) || 'submit_withdrawal' !== sanitize_text_field( wp_unslash( $_POST['wbte_ewb_action'] ) ) ) {
			return;
		}

		// Verify nonce.
		if ( ! isset( $_POST['wbte_ewb_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wbte_ewb_nonce'] ) ), 'wbte_ewb_withdrawal_form_nonce' ) ) {
			self::$errors->add( 'nonce_failed', __( 'Security check failed. Please try again.', 'wt-eu-withdrawal-button' ) );
			return;
		}

		// Sanitize input fields.
		$order_id     = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
		$order_number = isset( $_POST['order_number'] ) ? sanitize_text_field( wp_unslash( $_POST['order_number'] ) ) : '';
		$email        = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$first_name   = isset( $_POST['first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['first_name'] ) ) : '';
		$last_name    = isset( $_POST['last_name'] ) ? sanitize_text_field( wp_unslash( $_POST['last_name'] ) ) : '';
		$request_type = isset( $_POST['request_type'] ) ? sanitize_text_field( wp_unslash( $_POST['request_type'] ) ) : 'full';
		$reason       = isset( $_POST['reason'] ) ? sanitize_textarea_field( wp_unslash( $_POST['reason'] ) ) : '';

		// Sanitize selected items.
		$selected_items = array();
		if ( isset( $_POST['selected_items'] ) && is_array( $_POST['selected_items'] ) ) {
			$selected_items = array_map( 'absint', wp_unslash( $_POST['selected_items'] ) );
			$selected_items = array_filter( $selected_items );
		}

		// Validate request type.
		if ( ! in_array( $request_type, array( 'full', 'partial' ), true ) ) {
			$request_type = 'full';
		}

		// Validate reason if required.
		if ( 'yes' === Wbte_Ewb_Settings::get( 'reason_required', 'no' ) && empty( $reason ) ) {
			self::$errors->add( 'reason_required', __( 'Please provide a reason for your withdrawal request.', 'wt-eu-withdrawal-button' ) );
		}

		// Resolve the order based on logged-in status.
		$order = null;

		if ( is_user_logged_in() ) {
			// Logged-in user: validate via order_id.
			if ( ! $order_id ) {
				self::$errors->add( 'order_required', __( 'Please select an order.', 'wt-eu-withdrawal-button' ) );
				return;
			}

			$order = wc_get_order( $order_id );

			if ( ! $order ) {
				self::$errors->add( 'order_not_found', __( 'Order not found.', 'wt-eu-withdrawal-button' ) );
				return;
			}

			// Verify the current user owns this order.
			if ( (int) $order->get_customer_id() !== get_current_user_id() ) {
				self::$errors->add( 'order_ownership', __( 'You do not have permission to request withdrawal for this order.', 'wt-eu-withdrawal-button' ) );
				return;
			}

			$email        = $order->get_billing_email();
			$first_name   = $order->get_billing_first_name();
			$last_name    = $order->get_billing_last_name();
			$order_number = $order->get_order_number();
		} else {
			$guest_service = function_exists( 'wbte_ewb' ) ? wbte_ewb()->get( 'guest_withdrawal' ) : null;

			if ( ! $guest_service ) {
				self::$errors->add( 'service_unavailable', __( 'The withdrawal service is temporarily unavailable.', 'wt-eu-withdrawal-button' ) );
				return;
			}

			$result = $guest_service->queue_request(
				array(
					'order_number' => $order_number,
					'email'        => $email,
					'reason'       => $reason,
				)
			);

			if ( is_wp_error( $result ) ) {
				foreach ( $result->get_error_messages() as $message ) {
					self::$errors->add( 'submission_error', $message );
				}
				return;
			}

			$redirect_url = add_query_arg(
				array( 'wbte_ewb_guest_queued' => '1' ),
				wp_get_referer() ? wp_get_referer() : home_url()
			);

			wp_safe_redirect( esc_url_raw( $redirect_url ) );
			exit;
		}

		// Bail if any validation errors accumulated.
		if ( self::has_errors() ) {
			return;
		}

		// Build submission data for logged-in customers.
		$data = array(
			'order_id'       => $order_id,
			'order_number'   => $order_number,
			'email'          => $email,
			'first_name'     => $first_name,
			'last_name'      => $last_name,
			'request_type'   => $request_type,
			'selected_items' => $selected_items,
			'reason'         => $reason,
			'customer_id'    => get_current_user_id(),
		);

		$service = function_exists( 'wbte_ewb' ) ? wbte_ewb()->get( 'service' ) : null;

		if ( ! $service ) {
			self::$errors->add( 'service_unavailable', __( 'The withdrawal service is temporarily unavailable.', 'wt-eu-withdrawal-button' ) );
			return;
		}

		$result = $service->submit( $data );

		if ( is_wp_error( $result ) ) {
			foreach ( $result->get_error_messages() as $message ) {
				self::$errors->add( 'submission_error', $message );
			}
			return;
		}

		$redirect_url = add_query_arg(
			array( 'wbte_ewb_submitted' => '1' ),
			wp_get_referer() ? wp_get_referer() : home_url()
		);

		$transient_key = 'wbte_ewb_success_' . get_current_user_id();
		set_transient(
			$transient_key,
			__( 'Your withdrawal request has been submitted successfully. You will receive a confirmation email shortly.', 'wt-eu-withdrawal-button' ),
			60
		);

		wp_safe_redirect( esc_url_raw( $redirect_url ) );
		exit;
	}

	/**
	 * Check whether validation errors exist.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	public static function has_errors() {
		return ( self::$errors instanceof WP_Error ) && self::$errors->has_errors();
	}

	/**
	 * Retrieve all validation errors.
	 *
	 * @since 1.0.0
	 *
	 * @return WP_Error
	 */
	public static function get_errors() {
		if ( ! self::$errors instanceof WP_Error ) {
			self::$errors = new WP_Error();
		}

		return self::$errors;
	}

	/**
	 * Retrieve the success message from the transient.
	 *
	 * Deletes the transient after reading.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public static function get_success_message() {
		if ( ! empty( self::$success_message ) ) {
			return self::$success_message;
		}

		$user_key = is_user_logged_in() ? get_current_user_id() : '';

		// For guest, we cannot reliably retrieve the transient without the email.
		// Check the query arg instead.
		if ( isset( $_GET['wbte_ewb_submitted'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['wbte_ewb_submitted'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( $user_key ) {
				$transient_key     = 'wbte_ewb_success_' . $user_key;
				self::$success_message = get_transient( $transient_key );
				delete_transient( $transient_key );

				if ( self::$success_message ) {
					return self::$success_message;
				}
			}

			// Fallback generic message.
			return __( 'Your withdrawal request has been submitted successfully.', 'wt-eu-withdrawal-button' );
		}

		return '';
	}
}
