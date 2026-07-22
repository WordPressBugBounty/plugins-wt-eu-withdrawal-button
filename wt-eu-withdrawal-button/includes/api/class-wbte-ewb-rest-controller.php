<?php
/**
 * Abstract base REST controller.
 *
 * Provides shared helpers for all plugin REST endpoints:
 * response envelopes, permission checks, and namespace config.
 *
 * @package Wbte_Eu_Withdrawal_Button
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Wbte_Ewb_REST_Controller
 *
 * Abstract base class extending WP_REST_Controller that every
 * plugin endpoint controller inherits from.
 *
 * @since 1.0.0
 */
abstract class Wbte_Ewb_REST_Controller extends WP_REST_Controller {

	/**
	 * REST API namespace.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	protected $namespace = 'wbte-ewb/v1';

	/**
	 * Build a successful JSON envelope response.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed  $data    Response payload.
	 * @param string $message Optional human-readable message.
	 * @param int    $status  HTTP status code.
	 * @return WP_REST_Response
	 */
	protected function success_response( $data, $message = '', $status = 200 ) {
		return new WP_REST_Response(
			array(
				'success' => true,
				'data'    => $data,
				'message' => $message,
			),
			$status
		);
	}

	/**
	 * Build an error response using WP_Error.
	 *
	 * @since 1.0.0
	 *
	 * @param string $code    Machine-readable error code.
	 * @param string $message Human-readable error message.
	 * @param int    $status  HTTP status code.
	 * @return WP_Error
	 */
	protected function error_response( $code, $message, $status = 400 ) {
		return new WP_Error(
			$code,
			$message,
			array( 'status' => $status )
		);
	}

	/**
	 * Permission check for admin endpoints.
	 *
	 * Returns true if the current user has the `manage_woocommerce`
	 * or the custom `wbte_ewb_manage_withdrawals` capability.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error True on success, WP_Error on failure.
	 */
	public function admin_permission_check( $request ) {
		if ( current_user_can( 'manage_woocommerce' ) || current_user_can( 'wbte_ewb_manage_withdrawals' ) ) {
			return true;
		}

		return $this->error_response(
			'wbte_ewb_forbidden',
			__( 'You do not have permission to perform this action.', 'wt-eu-withdrawal-button' ),
			403
		);
	}

	/**
	 * Permission check for guest queue submissions.
	 *
	 * @since 1.1.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error True on success, WP_Error on failure.
	 */
	public function guest_queue_permission_check( $request ) {
		$nonce = $request->get_header( 'X-WP-Nonce' );

		if ( ! $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return $this->error_response(
				'wbte_ewb_invalid_nonce',
				__( 'Invalid security token. Please refresh the page and try again.', 'wt-eu-withdrawal-button' ),
				403
			);
		}

		if ( is_user_logged_in() ) {
			return $this->error_response(
				'wbte_ewb_guest_only',
				__( 'Logged-in customers should submit withdrawal requests from their account.', 'wt-eu-withdrawal-button' ),
				400
			);
		}

		return true;
	}

	/**
	 * Permission check for customer endpoints.
	 *
	 * Logged-in users are allowed immediately. Guest users must
	 * provide a valid order_number + email combination that matches
	 * an existing WooCommerce order.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error True on success, WP_Error on failure.
	 */
	public function customer_permission_check( $request ) {
		if ( is_user_logged_in() ) {
			return true;
		}

		$order_number = $request->get_param( 'order_number' );
		$email        = $request->get_param( 'email' );

		if ( empty( $order_number ) || empty( $email ) ) {
			return $this->error_response(
				'wbte_ewb_missing_credentials',
				__( 'Order number and email are required for guest access.', 'wt-eu-withdrawal-button' ),
				401
			);
		}

		$order_number = sanitize_text_field( $order_number );
		$email        = sanitize_email( $email );

		if ( ! is_email( $email ) ) {
			return $this->error_response(
				'wbte_ewb_invalid_email',
				__( 'Please provide a valid email address.', 'wt-eu-withdrawal-button' ),
				401
			);
		}

		// Resolve order number to an order.
		$order = $this->resolve_order_by_number( $order_number );

		if ( ! $order ) {
			return $this->error_response(
				'wbte_ewb_order_not_found',
				__( 'No order found matching the provided order number.', 'wt-eu-withdrawal-button' ),
				401
			);
		}

		$order_email = $order->get_billing_email();

		if ( strtolower( $email ) !== strtolower( $order_email ) ) {
			return $this->error_response(
				'wbte_ewb_email_mismatch',
				__( 'The email address does not match the order.', 'wt-eu-withdrawal-button' ),
				401
			);
		}

		$order_id = absint( $request->get_param( 'order_id' ) );

		if ( $order_id && (int) $order->get_id() !== $order_id ) {
			return $this->error_response(
				'wbte_ewb_order_mismatch',
				__( 'The order number does not match the requested order.', 'wt-eu-withdrawal-button' ),
				403
			);
		}

		return true;
	}

	/**
	 * Check whether a customer-facing order number belongs to the given order.
	 *
	 * @since 1.0.4
	 *
	 * @param WC_Order $order        WooCommerce order.
	 * @param string   $order_number Customer-facing order number.
	 * @return bool
	 */
	protected function order_number_matches_order( $order, $order_number ) {
		if ( ! $order instanceof WC_Order ) {
			return false;
		}

		$order_number = sanitize_text_field( (string) $order_number );

		if ( '' === $order_number ) {
			return false;
		}

		if ( (string) $order->get_order_number() === $order_number ) {
			return true;
		}

		$resolved = $this->resolve_order_by_number( $order_number );

		return $resolved instanceof WC_Order && (int) $resolved->get_id() === (int) $order->get_id();
	}

	/**
	 * Resolve an order number to a WC_Order object.
	 *
	 * Tries sequential order number lookup first, then falls back
	 * to loading by numeric ID.
	 *
	 * @since 1.0.0
	 *
	 * @param string $order_number The customer-facing order number.
	 * @return WC_Order|false The order object or false if not found.
	 */
	protected function resolve_order_by_number( $order_number ) {
		// Try sequential order number resolution if available.
		if ( class_exists( 'Wbte_Ewb_Sequential_Orders' ) ) {
			$seq = new Wbte_Ewb_Sequential_Orders();
			$order = $seq->find_order_by_number( $order_number );
			if ( $order ) {
				return $order;
			}
		}

		// Fallback: try treating the order number as the order ID.
		$order = wc_get_order( absint( $order_number ) );

		if ( $order && $order->get_id() ) {
			return $order;
		}

		return false;
	}
}
